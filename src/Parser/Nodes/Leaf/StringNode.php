<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\Branded;
use Le0daniel\PhpTsBindings\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Stringable;
use Throwable;

final readonly class StringNode implements NodeInterface, LeafNode, Coercible, Branded
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
        return 'string';
    }

    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_string($value) ? $value : $this->invalidType('string', $value, $context);
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        try {
            return is_string($value) || $value instanceof Stringable
                ? (string) $value
                : $this->invalidType('string', $value, $context);
        } catch (Throwable $throwable) {
            $context->addIssue(Issue::fromThrowable($throwable, [
                'node' => self::class,
                'message' => "Failed to serialize value of type: " . gettype($value),
                'value' => $value,
            ]));
            return Value::INVALID;
        }
    }

    public function brandName(): ?string
    {
        return $this->brand;
    }

    public function coerce(mixed $value): mixed
    {
        return (string) $value;
    }
}
