<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class BoolNode implements NodeInterface, LeafNode, Coercible
{
    use RejectsInvalidType;

    public function __toString(): string
    {
        return 'bool';
    }

    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_bool($value) ? $value : $this->invalidType('bool', $value, $context);
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_bool($value) ? $value : $this->invalidType('bool', $value, $context);
    }

    public function coerce(mixed $value): mixed
    {
        return match ($value) {
            'true', '1' => true,
            'false', '0' => false,
            default => $value,
        };
    }
}
