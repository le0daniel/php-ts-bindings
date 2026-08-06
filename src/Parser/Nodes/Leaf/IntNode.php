<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class IntNode implements Coercible, LeafNode, NodeInterface
{
    use RejectsInvalidType;

    #[Override]
    public function __toString(): string
    {
        return 'int';
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return 'new '.PHPExport::absolute(self::class).'()';
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_int($value) ? $value : $this->invalidType('int', $value, $context);
    }

    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_int($value) ? $value : $this->invalidType('int', $value, $context);
    }

    #[Override]
    public function coerce(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : $value;
    }
}
