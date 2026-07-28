<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\Branded;
use Le0daniel\PhpTsBindings\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class IntNode implements NodeInterface, LeafNode, Coercible, Branded
{
    use RejectsInvalidType;

    public function __construct(
        public ?string $brand = null,
    )
    {
    }

    /**
     * The brand is deliberately excluded here and in exportPhpCode(): it is code generation
     * metadata with no runtime impact, exactly as in ValueObjectNode.
     */
    public function __toString(): string
    {
        return 'int';
    }

    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_int($value) ? $value : $this->invalidType('int', $value, $context);
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_int($value) ? $value : $this->invalidType('int', $value, $context);
    }

    public function brandName(): ?string
    {
        return $this->brand;
    }

    public function coerce(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : $value;
    }
}
