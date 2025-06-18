<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use RuntimeException;

final class TypescriptDefinition
{
    public function toDefinition(NodeInterface $node, DefinitionTarget $target): string
    {
        if ($node instanceof LeafNode) {
            return $target === DefinitionTarget::INPUT ? $node->inputDefinition() : $node->outputDefinition();
        }

        return match ($node::class) {
            StructNode::class => $this->printStructNode($node, $target),
            UnionNode::class => $this->printUnionNode($node, $target),
            IntersectionNode::class => $this->printIntersectionNode($node, $target),
            ListNode::class => "Array<{$this->toDefinition($node->node, $target)}>",
            RecordNode::class => "Record<string,{$this->toDefinition($node->node, $target)}>",
            TupleNode::class => '[' . implode(',', array_map(fn(NodeInterface $node) => $this->toDefinition($node, $target), $node->types)) . ']',
            ConstraintNode::class, CustomCastingNode::class => $this->toDefinition($node->node, $target),
            default => throw new RuntimeException("Not implemented: " . $node::class),
        };
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
            fn(PropertyNode $property) => "{$property->name}:{$this->toDefinition($property->node, $target)};",
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