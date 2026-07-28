<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;

final class Nodes
{
    public static function getDeclaringNode(NodeInterface $node): NodeInterface
    {
        while ($node instanceof ConstraintNode || $node instanceof MetadataNode) {
            $node = $node->node;
        }
        return $node;
    }

    /**
     * Strips codegen metadata only. Unlike getDeclaringNode(), constraints stay attached — use
     * this where a ConstraintNode must remain visible, e.g. so a constrained array key is
     * rejected instead of silently losing its runtime validation.
     */
    public static function unwrapMetadata(NodeInterface $node): NodeInterface
    {
        while ($node instanceof MetadataNode) {
            $node = $node->node;
        }
        return $node;
    }

    /**
     * @param list<NodeInterface> $nodes
     * @return bool
     */
    public static function areAllNodesOfSameStructType(array $nodes): bool
    {
        /** @var StructPhpType|null $expectedStructNodeType */
        $expectedStructNodeType = null;

        $stack = $nodes;
        while ($node = array_pop($stack)) {
            if ($node instanceof UnionNode) {
                array_push($stack, ... $node->types);
                continue;
            }

            $declaredNode = self::getDeclaringNode($node);
            if (!$declaredNode instanceof StructNode) {
                return false;
            }

            $expectedStructNodeType ??= $declaredNode->phpType;
            if ($declaredNode->phpType !== $expectedStructNodeType) {
                return false;
            }
        }

        return true;
    }
}