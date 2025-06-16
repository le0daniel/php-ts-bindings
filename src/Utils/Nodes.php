<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;

final class Nodes
{
    public static function getDeclaringNode(NodeInterface $node): NodeInterface
    {
        while ($node instanceof ConstraintNode || $node instanceof NamedNode) {
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

        foreach ($nodes as $complexNode) {
            $node = self::getDeclaringNode($complexNode);
            if (!$node instanceof StructNode) {
                return false;
            }

            $expectedStructNodeType ??= $node->phpType;
            if ($node->phpType !== $expectedStructNodeType) {
                return false;
            }
        }

        return true;
    }
}