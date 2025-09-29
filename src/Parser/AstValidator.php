<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
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
use RuntimeException;

final class AstValidator
{
    public static function validate(NodeInterface $node): void
    {
        /** @var list<NodeInterface> $stack */
        $stack = [$node];

        while ($current = array_pop($stack)) {
            if ($current instanceof ValidatableNode) {
                $current->validate();
            }

            if ($current instanceof LeafNode) {
                continue;
            }

            match ($current::class) {
                ConstraintNode::class, CustomCastingNode::class, ListNode::class, NamedNode::class, PropertyNode::class, RecordNode::class => $stack[] = $current->node,
                TupleNode::class, IntersectionNode::class, UnionNode::class => array_push($stack, ...$current->types),
                StructNode::class => array_push($stack, ... $current->properties),
                default => throw new RuntimeException("Unexpected node: " . $current::class),
            };
        }
    }
}