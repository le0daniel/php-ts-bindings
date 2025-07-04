<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ReferencedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
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
    /** @var array<string, NodeInterface>  */
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
        if (file_put_contents($fileName, <<<PHP
<?php declare(strict_types=1);
return {$this->generateOptimizedCode($nodes)};
PHP) === false) {
            throw new RuntimeException("Could not write to file: {$fileName}");
        }
    }

    /**
     * @param array<string, NodeInterface|Closure(): NodeInterface> $nodes
     */
    public function generateOptimizedCode(array $nodes): string
    {
        if (array_any(array_keys($nodes), fn(string $key) => str_starts_with($key, '#'))) {
            throw new RuntimeException('The keys of the nodes MUST not start with a # character');
        }

        $this->dedupedNodes = [];

        $optimizedNodes = array_map(
            fn(Closure|NodeInterface $node) => $this->dedupeNode($node instanceof Closure ? $node() : $node),
            $nodes
        );

        $registryClass = PHPExport::absolute(CachedTypeRegistry::class);

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

        return "new {$registryClass}([{$factories}])";
    }

    /**
     * @template T of NodeInterface
     * @param T $node
     * @return T|ReferencedNode
     */
    private function dedupeNode(NodeInterface $node): NodeInterface
    {
        if ($node instanceof NamedNode) {
            return $this->dedupeNode($node->node);
        }

        if ($node instanceof ReferencedNode) {
            return $node;
        }

        if ($node instanceof LeafNode) {
            $identifier = '#leaf_' . sha1((string)$node);
            $this->dedupedNodes[$identifier] ??= $node;
            return new ReferencedNode($identifier, (string)$node, $this->registryVariableName);
        }

        if ($node instanceof PropertyNode) {
            $identifier = '#prop_' . sha1((string)$node);
            $this->dedupedNodes[$identifier] ??= new PropertyNode(
                $node->name,
                $this->dedupeNode($node->node),
                $node->isOptional,
                $node->propertyType
            );

            return new ReferencedNode($identifier, (string)$node, $this->registryVariableName);
        }

        // Deep optimization
        if ($node instanceof StructNode) {
            $deepOptimizedNode = new StructNode(
                $node->phpType,
                array_map($this->dedupeNode(...), $node->sortedProperties()),
            );
            $identifier = '#struct_' . sha1((string)$deepOptimizedNode);
            $this->dedupedNodes[$identifier] ??= $deepOptimizedNode;
            return new ReferencedNode($identifier, (string)$node, $this->registryVariableName);
        }

        // ToDo: Further optimization for example on union nodes with only Primitive Types or
        //       more intelligent node determination for better runtime performance.
        return match ($node::class) {
            ConstraintNode::class => $this->flattenConstraintNode($node),
            CustomCastingNode::class => new CustomCastingNode(
                $this->dedupeNode($node->node),
                $node->fullyQualifiedCastingClass,
                $node->strategy,
            ),
            ListNode::class => new ListNode(
                $this->dedupeNode($node->node),
            ),
            RecordNode::class => new RecordNode(
                $this->dedupeNode($node->node),
            ),
            TupleNode::class => new TupleNode(
                array_map($this->dedupeNode(...), $node->types),
            ),
            UnionNode::class => new UnionNode(
                array_map($this->dedupeNode(...), $node->types),
                $node->discriminator,
                $node->discriminatorMap,
            ),
            IntersectionNode::class => new IntersectionNode(
                array_map($this->dedupeNode(...), $node->types),
            ),
            default => throw new RuntimeException('Unknown node type: ' . $node::class),
        };
    }

    private function flattenConstraintNode(ConstraintNode $node): ConstraintNode
    {
        /** @var list<Constraint> $constraints */
        $constraints = [];
        while ($node instanceof ConstraintNode) {
            array_push($constraints, ...$node->constraints);
            $node = $node->node;
        }

        return new ConstraintNode(
            $this->dedupeNode($node),
            $constraints,
        );
    }
}