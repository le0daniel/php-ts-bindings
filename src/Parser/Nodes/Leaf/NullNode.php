<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class NullNode implements NodeInterface, LeafNode
{
    use RejectsInvalidType;

    public function __toString(): string
    {
        return 'null';
    }

    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_null($value) ? $value : $this->invalidType('null', $value, $context);
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_null($value) ? null : $this->invalidType('null', $value, $context);
    }
}
