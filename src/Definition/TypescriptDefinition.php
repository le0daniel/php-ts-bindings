<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Definition;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Definition\Data\Mode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Utils\Nodes;
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
            UnionNode::class => $this->joinUnionNode($node, $mode),
            ListNode::class => "Array<{$this->toDefinition($node->node, $mode)}>",
            RecordNode::class => "Record<string,{$this->toDefinition($node->node, $mode)}>",
            TupleNode::class => '[' . implode(',', array_map(fn(NodeInterface $node) => $this->toDefinition($node, $mode), $node->types)) . ']',
            ConstraintNode::class, CustomCastingNode::class => $this->toDefinition($node->node, $mode),
            IntersectionNode::class => $this->joinIntersectionType($node, $mode),
            default => throw new RuntimeException("Not implemented: " . $node::class),
        };
    }

    private function joinUnionNode(UnionNode $node, Mode $mode): string
    {
        return implode(
            '|', array_unique(array_map(
                function (NodeInterface $node) use ($mode) {
                    $definition = $this->toDefinition($node, $mode);
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

    private function joinIntersectionType(IntersectionNode $node, Mode $mode): string
    {
        return implode('&', array_map(
                function (NodeInterface $node) use ($mode) {
                    $definition = $this->toDefinition($node, $mode);
                    return Nodes::getDeclaringNode($node) instanceof UnionNode
                        ? "({$definition})" : $definition;
                },
                $node->types)
        );
    }
}