<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\Utils\Typescript;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use RuntimeException;

final readonly class TypescriptDefinitionGenerator
{
    public function __construct(
        private bool $emitBrandedTypes = false,
    )
    {
    }

    public function toDefinition(NodeInterface $node, DefinitionTarget $target): string
    {
        if ($node instanceof LeafNode) {
            if ($this->emitBrandedTypes && $node instanceof BuiltInNode && $node->brand) {
                $encodedBrand = json_encode($node->brand, JSON_THROW_ON_ERROR);
                return $target === DefinitionTarget::INPUT
                    ? "Branded<{$node->inputDefinition()},{$encodedBrand}>"
                    : "Branded<{$node->outputDefinition()},{$encodedBrand}>";
            }

            return $target === DefinitionTarget::INPUT ? $node->inputDefinition() : $node->outputDefinition();
        }

        return match ($node::class) {
            StructNode::class => $this->printStructNode($node, $target),
            UnionNode::class => $this->printUnionNode($node, $target),
            IntersectionNode::class => $this->printIntersectionNode($node, $target),
            ListNode::class => "Array<{$this->toDefinition($node->node, $target)}>",
            RecordNode::class => "Record<string,{$this->toDefinition($node->node, $target)}>",
            TupleNode::class => '[' . implode(',', array_map(fn(NodeInterface $node) => $this->toDefinition($node, $target), $node->types)) . ']',
            ConstraintNode::class => $this->toDefinition($node->node, $target),
            CustomCastingNode::class => $this->printCustomCastingNode($node, $target),
            default => throw new RuntimeException("Not implemented: " . $node::class),
        };
    }

    private function printCustomCastingNode(CustomCastingNode $node, DefinitionTarget $target): string
    {
        // Returns if an object can ever be targeted for input.
        return $target === DefinitionTarget::INPUT && $node->strategy === ObjectCastStrategy::NEVER
            ? 'never'
            : $this->toDefinition($node->node, $target);
    }

    private function printStructNode(StructNode $node, DefinitionTarget $target): string
    {
        $filteredProperties = array_filter(
            $node->properties,
            fn(NodeInterface $property) => $target === DefinitionTarget::INPUT
                ? $property->propertyType->isInput()
                : $property->propertyType->isOutput(),
        );

        $properties = array_map(
            function (PropertyNode $property) use ($target): string {
                return Typescript::objectKey($property->name, $property->isOptional) . ":{$this->toDefinition($property->node, $target)};";
            },
            $filteredProperties,
        );

        return "{" . implode("", $properties) . "}";
    }

    /** @param UnionNode<NodeInterface> $node */
    private function printUnionNode(UnionNode $node, DefinitionTarget $target): string
    {
        return implode(
            '|', array_unique(array_map(
                function (NodeInterface $node) use ($target) {
                    $definition = $this->toDefinition($node, $target);
                    $definingNode = Nodes::getDeclaringNode($node);

                    return match ($definingNode::class) {
                        UnionNode::class, IntersectionNode::class => "({$definition})",
                        default => $definition,
                    };
                },
                $node->types
            ))
        );
    }

    private function printIntersectionNode(IntersectionNode $node, DefinitionTarget $target): string
    {
        return implode('&', array_map(
                function (NodeInterface $node) use ($target) {
                    $definition = $this->toDefinition($node, $target);
                    return Nodes::getDeclaringNode($node) instanceof UnionNode
                        ? "({$definition})" : $definition;
                },
                $node->types)
        );
    }
}