<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;
use Stringable;
use Throwable;

final readonly class StringNode implements NodeInterface, LeafNode, Coercible
{
    use RejectsInvalidType;

    #[Override]
    public function __toString(): string
    {
        return 'string';
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return 'new ' . PHPExport::absolute(self::class) . '()';
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        return is_string($value) ? $value : $this->invalidType('string', $value, $context);
    }

    #[Override]
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

    #[Override]
    public function coerce(mixed $value): mixed
    {
        return (string) $value;
    }
}
