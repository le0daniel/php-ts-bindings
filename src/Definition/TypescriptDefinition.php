<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Definition;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Definition\Data\Mode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use RuntimeException;

final class TypescriptDefinition
{
    public function toDefinition(NodeInterface $node, Mode $mode): string
    {
        if ($node instanceof LeafNode) {
            return $mode === Mode::INPUT ? $node->inputDefinition() : $node->outputDefinition();
        }

        if ($node instanceof StructNode) {
            $filteredProperties = array_filter(
                $node->properties,
                fn(NodeInterface $property) => $mode === Mode::INPUT
                    ? $property->propertyType->isInput()
                    : $property->propertyType->isOutput(),
            );

            $properties = array_map(
                fn(PropertyNode $property) => "{$property->name}:{$this->toDefinition($property->node, $mode)};",
                $filteredProperties,
            );

            return "{" . implode("", $properties) . "}";
        }

        return match ($node::class) {
            UnionNode::class => implode('|', array_map(fn(NodeInterface $node) => $this->toDefinition($node, $mode), $node->types)),
            ListNode::class => "Array<{$this->toDefinition($node->node, $mode)}>",
            RecordNode::class => "Record<string,{$this->toDefinition($node->node, $mode)}>",
            TupleNode::class => '[' . implode(',', array_map(fn(NodeInterface $node) => $this->toDefinition($node, $mode), $node->types)) . ']',
            ConstraintNode::class, CustomCastingNode::class => $this->toDefinition($node->node, $mode),
            default => throw new RuntimeException("Not implemented: " . $node::class),
        };
    }
}