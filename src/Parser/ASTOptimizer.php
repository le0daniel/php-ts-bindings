<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Registry\SchemaRegistry;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\LazyStructNode;
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

    /**
     * @param array<string, NodeInterface> $nodes
     */
    public function writeToFile(string $fileName, array $nodes): void
    {
        $this->dedupedNodes = [];

        $optimizedNodes = array_map($this->dedupeNode(...), $nodes);

        $registryClass = PHPExport::absolute(SchemaRegistry::class);
        $dedupedAsString = implode(',', Arrays::mapWithKeys(
            $this->dedupedNodes,
            fn(string $key, NodeInterface $node) => "'{$key}' => static fn({$registryClass} \$registry) => {$node->exportPhpCode()}",
        ));
        $nodeInterfaceClass = PHPExport::absolute(NodeInterface::class);
        $structClass = PHPExport::absolute(StructNode::class);

        $optimizedNodesFactories = implode(',', Arrays::mapWithKeys(
            $optimizedNodes,
            fn(string $key, NodeInterface $ast) => PHPExport::export($key) . " => static fn() => {$ast->exportPhpCode()}"
        ));

        $content = [
            '<?php declare(strict_types=1);',
            "/** @var {$registryClass}<{$structClass}> \$registry */",
            "\$registry = new {$registryClass}([{$dedupedAsString}]);",
            '',
            "/** @return array<string, callable(): {$nodeInterfaceClass}> */",
            "return [{$optimizedNodesFactories}];",
        ];

        if (file_put_contents($fileName, implode("\n", $content)) === false) {
            throw new RuntimeException("Could not write to file: {$fileName}");
        }
    }

    /**
     * @template T of NodeInterface
     * @param T $node
     * @return T|LazyStructNode
     */
    private function dedupeNode(NodeInterface $node): NodeInterface
    {
        if ($node instanceof LazyStructNode) {
            return $node;
        }

        if ($node instanceof LeafNode) {
            $identifier = '__Leaf_' . sha1((string)$node);
            $this->dedupedNodes[$identifier] = $node;

            return new LazyStructNode($identifier, (string)$node);
        }

        // Deep optimization
        if ($node instanceof StructNode) {
            $deepOptimizedNode = new StructNode(
                $node->phpType,
                array_map($this->dedupeNode(...), $node->sortedProperties()),
            );
            $identifier = '__Struct_' . sha1((string)$deepOptimizedNode);
            $this->dedupedNodes[$identifier] = $deepOptimizedNode;
            return new LazyStructNode($identifier, (string)$node);
        }

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
            PropertyNode::class => new PropertyNode(
                $node->name,
                $this->dedupeNode($node->type),
                $node->isOptional,
                $node->propertyType,
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