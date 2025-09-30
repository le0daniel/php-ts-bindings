<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use UnitEnum;

final readonly class LiteralNode implements NodeInterface, LeafNode, Coercible
{
    /**
     * @param string|bool|int|float|null|UnitEnum $value
     */
    public function __construct(
        public LiteralType $type,
        public mixed       $value,
    )
    {}

    public function __toString(): string
    {
        return match ($this->type) {
            LiteralType::BOOL => $this->value ? 'literal<true>' : 'literal<false>',
            LiteralType::STRING => "literal<'{$this->value}'>",
            LiteralType::ENUM_CASE => "enum-value<{$this->value->name}>",
            LiteralType::NULL => 'literal<null>',
            LiteralType::INT, LiteralType::FLOAT => "literal<{$this->value}>",
        };
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = PHPExport::exportEnumCase($this->type);

        if ($this->type === LiteralType::ENUM_CASE) {
            $enumCase = PHPExport::exportEnumCase($this->value);
            return "new {$className}({$type}, {$enumCase})";
        }

        $value = var_export($this->value, true);
        return "new {$className}({$type}, {$value})";
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        if ($this->type !== LiteralType::ENUM_CASE) {
            if ($value !== $this->value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => "Expected literal value: {$this->value}, got: {$value}",
                    ]
                ));
                return Value::INVALID;
            }

            return $this->value;
        }

        $name = $this->value->name;
        return $value === $name ? $this->value : Value::INVALID;
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        if ($this->type === LiteralType::ENUM_CASE) {
            return $value === $this->value ? $this->value->name : Value::INVALID;
        }

        return $value === $this->value ? $this->value : Value::INVALID;
    }

    /**
     * @throws \JsonException
     */
    public function inputDefinition(): string
    {
        return match ($this->type) {
            LiteralType::BOOL => $this->value ? 'true' : 'false',
            LiteralType::ENUM_CASE => json_encode($this->value->name, JSON_THROW_ON_ERROR),
            default => json_encode($this->value, JSON_THROW_ON_ERROR),
        };
    }

    /**
     * @throws \JsonException
     */
    public function outputDefinition(): string
    {
        return $this->inputDefinition();
    }

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