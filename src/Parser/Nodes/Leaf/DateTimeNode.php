<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;

final readonly class DateTimeNode implements NodeInterface, LeafNode, ValidatableNode
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

    public function __toString(): string
    {
        return $this->dateTimeClass . "<{$this->format}>";
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $dateTimeClass = PHPExport::absolute($this->dateTimeClass);
        $format = $this->format === DateTimeInterface::ATOM
            ? ''
            : ',' . PHPExport::export($this->format);
        return "new {$className}({$dateTimeClass}::class{$format})";
    }

    public function parseValue(mixed $value, ExecutionContext $context): DateTimeInterface|Value
    {
        if ($value instanceof DateTimeInterface) {
            // @phpstan-ignore-next-line
            return $this->dateTimeClass::createFromInterface($value);
        }

        if (!is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected value of type string, got: " . gettype($value),
                ]
            ));
            return Value::INVALID;
        }

        try {
            $parsed = DateTimeImmutable::createFromFormat($this->format, $value);
            if ($parsed->format($this->format) !== $value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_DATE_FORMAT,
                    [
                        'format' => $this->format,
                        'value' => $value,
                    ],
                ));
                return Value::INVALID;
            }

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

    public function inputDefinition(): string
    {
        return "string";
    }

    public function outputDefinition(): string
    {
        return "string";
    }

    public function validate(): void
    {
        if (!method_exists($this->dateTimeClass, 'createFromInterface')) {
            throw new InvalidArgumentException("DateTime class {$this->dateTimeClass} does not have a createFromInterface method");
        }
    }
}