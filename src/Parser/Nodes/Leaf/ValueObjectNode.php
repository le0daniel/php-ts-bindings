<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BackingType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;
use Throwable;

/**
 * A user defined value object backed by a single string or int.
 *
 * The class opts in by implementing StringValueObject or IntValueObject. On the wire it is
 * indistinguishable from its backing primitive; a #[Brand] (carried by a wrapping MetadataNode)
 * is what keeps the two apart on the TypeScript side.
 */
final readonly class ValueObjectNode implements NodeInterface, LeafNode, Coercible
{
    /**
     * @param class-string<StringValueObject|IntValueObject> $className
     */
    public function __construct(
        public string      $className,
        public BackingType $backingType,
    )
    {
    }

    #[Override]
    public function __toString(): string
    {
        return "valueObject<{$this->className},{$this->backingType->value}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $valueObjectClass = PHPExport::absolute($this->className);
        $backingType = PHPExport::exportEnumCase($this->backingType);

        return "new {$className}({$valueObjectClass}::class, {$backingType})";
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        if ($this->backingType === BackingType::STRING) {
            if (!is_string($value)) {
                $context->addIssue($this->invalidBackingTypeIssue($value));
                return Value::INVALID;
            }

            try {
                /** @var class-string<StringValueObject> $className */
                $className = $this->className;
                return $className::fromStringValue($value);
            } catch (Throwable $throwable) {
                $this->addRejectionIssues($throwable, $value, $context);
                return Value::INVALID;
            }
        }

        if (!is_int($value)) {
            $context->addIssue($this->invalidBackingTypeIssue($value));
            return Value::INVALID;
        }

        try {
            /** @var class-string<IntValueObject> $className */
            $className = $this->className;
            return $className::fromIntValue($value);
        } catch (Throwable $throwable) {
            $this->addRejectionIssues($throwable, $value, $context);
            return Value::INVALID;
        }
    }

    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        if ($this->backingType === BackingType::STRING) {
            if (!$value instanceof StringValueObject || !is_a($value, $this->className)) {
                $context->addIssue($this->notAnInstanceIssue($value));
                return Value::INVALID;
            }

            try {
                return $value->toStringValue();
            } catch (Throwable $throwable) {
                $context->addIssue($this->failedToSerializeIssue($throwable));
                return Value::INVALID;
            }
        }

        if (!$value instanceof IntValueObject || !is_a($value, $this->className)) {
            $context->addIssue($this->notAnInstanceIssue($value));
            return Value::INVALID;
        }

        try {
            return $value->toIntValue();
        } catch (Throwable $throwable) {
            $context->addIssue($this->failedToSerializeIssue($throwable));
            return Value::INVALID;
        }
    }

    #[Override]
    public function coerce(mixed $value): mixed
    {
        if ($this->backingType === BackingType::STRING) {
            // Unlike StringNode, only scalars are cast: (string) on an array or a non
            // Stringable object throws, and coerce() runs outside the executor's try/catch.
            return is_scalar($value) ? (string)$value : $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int)$value
            : $value;
    }

    private function invalidBackingTypeIssue(mixed $value): Issue
    {
        return new Issue(
            IssueMessage::INVALID_TYPE,
            debugInfo: [
                'message' => "Expected value of type {$this->backingType->value} for {$this->className}, got: " . get_debug_type($value),
                'node' => self::class,
            ],
        );
    }

    /**
     * A throwing factory means the incoming value was rejected, which is a validation failure and
     * not a server fault. Issue::fromThrowable() is deliberately not used on this path: it maps to
     * IssueMessage::INTERNAL_ERROR, which would present bad user input as a server error.
     *
     * A ValidationException is the factory saying what is wrong, so its messages are reported
     * verbatim - one issue each. Anything else has no message fit for a client, so it collapses to
     * the generic key and keeps its own message in the debug info.
     *
     * That generic key is INVALID_VALUE, not INVALID_TYPE: parseValue() proved the backing type
     * before calling the factory, so the string or int is exactly what was declared. What the
     * factory refused is the value.
     */
    private function addRejectionIssues(Throwable $throwable, mixed $value, ExecutionContext $context): void
    {
        if ($throwable instanceof ValidationException) {
            foreach ($throwable->toIssues($this->rejectionDebugInfo($value, $throwable)) as $issue) {
                $context->addIssue($issue);
            }
            return;
        }

        $context->addIssue(new Issue(
            IssueMessage::INVALID_VALUE,
            debugInfo: $this->rejectionDebugInfo($value, $throwable),
            exception: $throwable,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function rejectionDebugInfo(mixed $value, Throwable $throwable): array
    {
        return [
            'message' => "Value rejected by {$this->className}: {$throwable->getMessage()}",
            'node' => self::class,
            'value' => $value,
        ];
    }

    private function notAnInstanceIssue(mixed $value): Issue
    {
        return new Issue(
            IssueMessage::INVALID_TYPE,
            debugInfo: [
                'message' => "Expected instance of {$this->className}, got: " . get_debug_type($value),
                'node' => self::class,
            ],
        );
    }

    /**
     * On the serialize path the value came from the server, so a throwing accessor is a genuine
     * internal error. This mirrors StringNode::serializeValue().
     */
    private function failedToSerializeIssue(Throwable $throwable): Issue
    {
        return Issue::fromThrowable($throwable, [
            'message' => "Failed to serialize {$this->className}",
            'node' => self::class,
        ]);
    }
}
