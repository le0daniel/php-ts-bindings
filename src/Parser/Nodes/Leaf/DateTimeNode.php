<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use DateTimeImmutable;
use DateTimeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;
use Throwable;

final readonly class DateTimeNode implements NodeInterface, LeafNode
{
    /**
     * @param class-string<DateTimeInterface> $dateTimeClass
     * @param string $format
     */
    public function __construct(
        public string $dateTimeClass,
        public string $format = DateTimeInterface::ATOM,
    )
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->dateTimeClass . "<{$this->format}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $dateTimeClass = PHPExport::absolute($this->dateTimeClass);
        $format = $this->format === DateTimeInterface::ATOM
            ? ''
            : ',' . PHPExport::export($this->format);
        return "new {$className}({$dateTimeClass}::class{$format})";
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): DateTimeInterface|Value
    {
        if (!is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected value of type string, got: " . gettype($value),
                ]
            ));
            return Value::INVALID;
        }

        // The trailing `|` resets every field the format did not parse to a zero-like value.
        // Without it, `Y-m-d` inherits the current clock time and the result is not deterministic.
        $parsed = DateTimeImmutable::createFromFormat("{$this->format}|", $value);

        // createFromFormat() is lenient: it accepts `2025-1-1` for `Y-m-d` without so much as a
        // warning, and rolls `2025-02-30` over into March. Re-formatting the result and comparing
        // it to the input is the only check that holds the value to the format exactly.
        if ($parsed === false || $parsed->format($this->format) !== $value) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected a date string of format '{$this->format}', got: {$value}",
                ]
            ));
            return Value::INVALID;
        }

        try {
            // @phpstan-ignore-next-line
            return $this->dateTimeClass::createFromInterface($parsed);
        } catch (Throwable $exception) {
            $context->addIssue(Issue::fromThrowable($exception));
            return Value::INVALID;
        }
    }

    /**
     * @return string|Value::INVALID
     */
    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): string|Value
    {
        if (!$value instanceof DateTimeInterface) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected instance of DateTimeInterface, got: " . gettype($value),
                ],
            ));
            return Value::INVALID;
        }
        return $value->format($this->format);
    }

}