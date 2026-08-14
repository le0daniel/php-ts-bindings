<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;
use UnitEnum;

final readonly class LiteralNode implements Coercible, LeafNode
{
    /**
     * $type and $value must agree; every method below reads one to interpret the other. Checked here
     * rather than trusted, because a mismatch is constructible - `new LiteralNode(ENUM_CASE, 'x')`
     * used to build fine and then fail much later, while reading ->name off a string.
     *
     * @param  string|bool|int|float|null|UnitEnum  $value
     */
    public function __construct(
        public LiteralType $type,
        public mixed $value,
    ) {
        $agrees = match ($type) {
            LiteralType::ENUM_CASE => $value instanceof UnitEnum,
            LiteralType::STRING => is_string($value),
            LiteralType::INT => is_int($value),
            LiteralType::FLOAT => is_float($value),
            LiteralType::BOOL => is_bool($value),
            LiteralType::NULL => $value === null,
        };

        if (! $agrees) {
            throw new ParserException(
                "Literal of type {$type->value} cannot hold a ".get_debug_type($value).'.'
            );
        }
    }

    /**
     * The value as the ENUM_CASE branch knows it to be. The constructor guarantees the correlation;
     * this only makes it visible to the type checker.
     */
    private function enumValue(): UnitEnum
    {
        assert($this->value instanceof UnitEnum);

        return $this->value;
    }

    /**
     * The value of a LiteralType::STRING literal. Callers that have checked $type can read the
     * string without re-deriving that fact.
     */
    public function stringValue(): string
    {
        assert($this->type === LiteralType::STRING && is_string($this->value));

        return $this->value;
    }

    private function scalarValue(): string|int|float
    {
        assert(is_string($this->value) || is_int($this->value) || is_float($this->value));

        return $this->value;
    }

    #[Override]
    public function __toString(): string
    {
        return match ($this->type) {
            LiteralType::BOOL => $this->value ? 'literal<true>' : 'literal<false>',
            LiteralType::STRING => "literal<'{$this->scalarValue()}'>",
            LiteralType::ENUM_CASE => 'enum-value<'.$this->enumValue()->name.'@'.$this->enumValue()::class.'>',
            LiteralType::NULL => 'literal<null>',
            LiteralType::INT => "literal<{$this->scalarValue()}>",
            // Rendered via var_export so 1.0 stays distinguishable from 1.
            LiteralType::FLOAT => 'literal<'.var_export($this->value, true).'>',
        };
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = PHPExport::exportEnumCase($this->type);

        if ($this->type === LiteralType::ENUM_CASE) {
            $enumCase = PHPExport::exportEnumCase($this->enumValue());

            return "new {$className}({$type}, {$enumCase})";
        }

        $value = var_export($this->value, true);

        return "new {$className}({$type}, {$value})";
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        if ($this->type !== LiteralType::ENUM_CASE) {
            if ($value !== $this->value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => 'Expected literal value: '.var_export($this->value, true)
                            .', got: '.get_debug_type($value),
                    ]
                ));

                return Value::INVALID;
            }

            return $this->value;
        }

        if ($value === $this->enumValue()->name) {
            return $this->value;
        }

        return $this->notTheLiteral($this->enumValue()->name, $value, $context);
    }

    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        if ($value !== $this->value) {
            return $this->notTheLiteral($this->value, $value, $context);
        }

        return $this->type === LiteralType::ENUM_CASE ? $this->enumValue()->name : $this->value;
    }

    private function notTheLiteral(mixed $expected, mixed $value, ExecutionContext $context): Value
    {
        $context->addIssue(new Issue(
            IssueMessage::INVALID_TYPE,
            [
                'message' => 'Expected literal value: '.var_export($expected, true)
                    .', got: '.var_export($value, true),
            ]
        ));

        return Value::INVALID;
    }

    #[Override]
    public function coerce(mixed $value): mixed
    {
        return match ($this->type) {
            LiteralType::BOOL => match ($value) {
                'true', '1' => true,
                'false', '0' => false,
                default => $value,
            },
            LiteralType::INT => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? (int) $value : $value,
            LiteralType::FLOAT => filter_var($value, FILTER_VALIDATE_INT) !== false || filter_var($value, FILTER_VALIDATE_FLOAT) !== false
                ? (float) $value : $value,
            default => $value,
        };
    }
}
