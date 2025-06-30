<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Stringable;
use Throwable;

readonly class BuiltInNode implements NodeInterface, LeafNode
{

    public function __construct(public BuiltInType $type)
    {
    }

    public function __toString(): string
    {
        return $this->type->value;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(BuiltInNode::class);
        $type = PHPExport::exportEnumCase($this->type);

        return implode('', [
            "new {$className}($type)"
        ]);
    }

    public function parseValue(mixed $value, ExecutionContext $context): mixed
    {
        $result = match ($this->type) {
            BuiltInType::STRING => is_string($value) ? $value : Value::INVALID,
            BuiltInType::INT => is_int($value) ? $value : Value::INVALID,
            BuiltInType::BOOL => is_bool($value) ? $value : Value::INVALID,
            BuiltInType::NULL => is_null($value) ? $value : Value::INVALID,
            BuiltInType::FLOAT => is_float($value) || is_int($value) ? $value : Value::INVALID,
            BuiltInType::MIXED => $value,
        };

        if ($result === Value::INVALID) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected value of type {$this->type->value}, got: " . gettype($value),
                ]
            ));
            return Value::INVALID;
        }

        return $result;
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        try {
            $value = match ($this->type) {
                BuiltInType::STRING => is_string($value) || $value instanceof Stringable
                    ? (string) $value
                    : Value::INVALID,
                BuiltInType::INT => is_int($value)
                    ? $value
                    : Value::INVALID,
                BuiltInType::BOOL => is_bool($value)
                    ? $value
                    : Value::INVALID,
                BuiltInType::NULL => is_null($value)
                    ? null
                    : Value::INVALID,
                BuiltInType::FLOAT => is_numeric($value)
                    ? (float) $value
                    : Value::INVALID,
                BuiltInType::MIXED => $value,
            };

            if ($value === Value::INVALID) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => "Expected value of type {$this->type->value}, got: " . gettype($value),
                    ]
                ));
            }
            return $value;
        } catch (Throwable $throwable) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected value of type {$this->type->value}, got: " . gettype($value),
                    'exception' => $throwable,
                ],
            ));
            return Value::INVALID;
        }
    }

    public function inputDefinition(): string
    {
        return match ($this->type) {
            BuiltInType::STRING => 'string',
            BuiltInType::INT, BuiltInType::FLOAT => 'number',
            BuiltInType::BOOL => 'boolean',
            BuiltInType::NULL => 'null',
            BuiltInType::MIXED => 'unknown',
        };
    }

    public function outputDefinition(): string
    {
        return $this->inputDefinition();
    }
}