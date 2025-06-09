<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Registry\SchemaRegistry;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\LazyReferencedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use RuntimeException;

final class ASTOptimizer
{
    private array $dedupedNodes = [];

    public function __construct(
        private readonly string $registryVariableName = 'registry',
    )
    {
    }

    /**
     * @param array<string, NodeInterface> $nodes
     */
    public function optimizeAndWriteToFile(string $fileName, array $nodes): void
    {
        if (file_put_contents($fileName, $this->generateOptimizedCode($nodes)) === false) {
            throw new RuntimeException("Could not write to file: {$fileName}");
        }
    }

    /**
     * @param array<string, NodeInterface> $nodes
     */
    public function generateOptimizedCode(array $nodes): string
    {
        if (array_any(array_keys($nodes), fn(string $key) => str_starts_with($key, '#'))) {
            throw new RuntimeException('The keys of the nodes MUST not start with a # character');
        }

        $this->dedupedNodes = [];
        $optimizedNodes = array_map($this->dedupeNode(...), $nodes);
        $registryClass = PHPExport::absolute(SchemaRegistry::class);

        $dedupedAsString = Arrays::mapWithKeys(
            $this->dedupedNodes,
            fn(string $key, NodeInterface $node) => PHPExport::export($key) . " => static fn({$registryClass} \${$this->registryVariableName}) => {$node->exportPhpCode()}",
        );

        $optimizedNodesFactories = Arrays::mapWithKeys(
            $optimizedNodes,
            fn(string $key, NodeInterface $ast) => PHPExport::export($key) . " => static fn({$registryClass} \${$this->registryVariableName}) => {$ast->exportPhpCode()}"
        );

        $factories = implode(',', [
            ... $dedupedAsString,
            ... $optimizedNodesFactories,
        ]);

        $content = [
            '<?php declare(strict_types=1);',
            "return new {$registryClass}([{$factories}]);",
        ];

        return implode("\n", $content);
    }

    /**
     * @template T of NodeInterface
     * @param T $node
     * @return T|LazyReferencedNode
     */
    private function dedupeNode(NodeInterface $node): NodeInterface
    {
        if ($node instanceof LazyReferencedNode) {
            return $node;
        }

        if ($node instanceof LeafNode) {
            $identifier = '#leaf_' . sha1((string)$node);
            $this->dedupedNodes[$identifier] = $node;
            return new LazyReferencedNode($identifier, (string)$node, $this->registryVariableName);
        }

        if ($node instanceof PropertyNode) {
            $optimizedNode = new PropertyNode(
                $node->name,
                $this->dedupeNode($node->type),
                $node->isOptional,
                $node->propertyType
            );

            $identifier = '#prop_' . sha1((string)$optimizedNode);
            $this->dedupedNodes[$identifier] = $optimizedNode;
            return new LazyReferencedNode($identifier, (string)$node, $this->registryVariableName);
        }

        // Deep optimization
        if ($node instanceof StructNode) {
            $deepOptimizedNode = new StructNode(
                $node->phpType,
                array_map($this->dedupeNode(...), $node->sortedProperties()),
            );
            $identifier = '#struct_' . sha1((string)$deepOptimizedNode);
            $this->dedupedNodes[$identifier] = $deepOptimizedNode;
            return new LazyReferencedNode($identifier, (string)$node, $this->registryVariableName);
        }

        // ToDo: Further optimization for example on union nodes with only Primitive Types or
        //       more intelligent node determination for better runtime performance.
        return match ($node::class) {
            ConstraintNode::class => new ConstraintNode(
                $this->dedupeNode($node->node),
                $node->constraints,
            ),
            CustomCastingNode::class => new CustomCastingNode(
                $this->dedupeNode($node->node),
                $node->fullyQualifiedCastingClass,
                $node->strategy,
            ),
            ListNode::class => new ListNode(
                $this->dedupeNode($node->type),
            ),
            RecordNode::class => new RecordNode(
                $this->dedupeNode($node->type),
            ),
            TupleNode::class => new TupleNode(
                array_map($this->dedupeNode(...), $node->types),
            ),
            UnionNode::class => new UnionNode(
                array_map($this->dedupeNode(...), $node->types),
                $node->discriminator,
                $node->discriminatorMap,
            ),
            default => throw new RuntimeException('Unknown node type: ' . $node::class),
        };
    }
}