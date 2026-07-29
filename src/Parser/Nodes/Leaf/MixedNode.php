<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class MixedNode implements NodeInterface, LeafNode
{
    public function __toString(): string
    {
        return 'mixed';
    }

    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return $value;
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        return $value;
    }
}
