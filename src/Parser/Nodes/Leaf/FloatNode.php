<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class FloatNode implements Coercible, LeafNode, NodeInterface
{
    use RejectsInvalidType;

    #[Override]
    public function __toString(): string
    {
        return 'float';
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return 'new '.PHPExport::absolute(self::class).'()';
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_float($value) || is_int($value)
            ? $value
            : $this->invalidType('float', $value, $context);
    }

    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        // Deliberately the same test parseValue() makes. Serialization proves the declared type;
        // accepting a numeric string here would repair the application's own output instead of
        // reporting it, and is_numeric() would let " 1e3" through as well.
        return is_float($value) || is_int($value)
            ? (float) $value
            : $this->invalidType('float', $value, $context);
    }

    #[Override]
    public function coerce(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false || filter_var($value, FILTER_VALIDATE_FLOAT) !== false
            ? (float) $value
            : $value;
    }
}
