<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class NullNode implements LeafNode, NodeInterface
{
    use RejectsInvalidType;

    #[Override]
    public function __toString(): string
    {
        return 'null';
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return 'new '.PHPExport::absolute(self::class).'()';
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_null($value) ? $value : $this->invalidType('null', $value, $context);
    }

    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_null($value) ? null : $this->invalidType('null', $value, $context);
    }
}
