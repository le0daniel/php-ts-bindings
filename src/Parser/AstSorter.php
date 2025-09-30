<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
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

final class AstSorter
{

    /**
     * @template T of NodeInterface
     * @param T $node
     * @return T
     */
    public static function sort(NodeInterface $node): NodeInterface
    {
        // Leafs are not sortable
        if ($node instanceof LeafNode) {
            return $node;
        }

        return match ($node::class) {
            ConstraintNode::class => new ConstraintNode(
                self::sort($node->node), $node->constraints,
            ),
            CustomCastingNode::class => new CustomCastingNode(
                self::sort($node->node),
                $node->fullyQualifiedCastingClass,
                $node->strategy,
            ),
            IntersectionNode::class => new IntersectionNode(
                array_map(self::sort(...), $node->types),
            ),
            ListNode::class => new ListNode(self::sort($node->node)),
            NamedNode::class => new NamedNode(self::sort($node->node), $node->name),
            PropertyNode::class => new PropertyNode($node->name, self::sort($node->node), $node->isOptional, $node->propertyType),
            RecordNode::class => new RecordNode(self::sort($node->node)),
            StructNode::class => new StructNode($node->phpType, array_map(self::sort(...), $node->sortedProperties())),
            TupleNode::class => new TupleNode(array_map(self::sort(...), $node->types)),
            UnionNode::class => new UnionNode(array_map(self::sort(...), $node->types), $node->discriminator, $node->discriminatorMap),
            default => $node
        };
    }

}