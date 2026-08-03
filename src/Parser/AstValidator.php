<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;

final readonly class AstValidator
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
                ConstraintNode::class, CustomCastingNode::class, ListNode::class, MetadataNode::class, PropertyNode::class, RecordNode::class => $stack[] = $current->node,
                TupleNode::class, IntersectionNode::class, UnionNode::class => array_push($stack, ...$current->nodes),
                StructNode::class => array_push($stack, ... $current->properties),
                default => throw new ParserException("Unexpected node: " . $current::class),
            };
        }
    }
}