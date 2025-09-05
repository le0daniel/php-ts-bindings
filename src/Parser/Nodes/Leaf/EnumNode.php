<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use JsonException;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use UnitEnum;

final readonly class EnumNode implements NodeInterface, LeafNode
{
    /**
     * @param class-string<UnitEnum> $enumClassName
     */
    public function __construct(
        private string $enumClassName,
    )
    {
    }

    public function __toString(): string
    {
        return "enum<{$this->enumClassName}>";
    }

    public function exportPhpCode(): string
    {
        $enumClass = PHPExport::absolute($this->enumClassName);
        $className = PHPExport::absolute(self::class);
        return "new {$className}({$enumClass}::class)";
    }

    public function parseValue(mixed $value, ExecutionContext $context): UnitEnum|Value
    {
        /** ToDo: Error handling */
        if (!is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    "message" => "Expected string name of enum {$this->enumClassName}, got: " . gettype($value),
                    "value" => $value,
                ]
            ));
            return Value::INVALID;
        }

        $cases = $this->enumClassName::cases();
        foreach ($cases as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        $context->addIssue(new Issue(
            \Le0daniel\PhpTsBindings\Executor\Data\IssueMessage::INVALID_TYPE,
            [
                "message" => "Expected string name of enum {$this->enumClassName}, got: '{$value}'",
                "value" => $value,
            ]
        ));
        return Value::INVALID;
    }

    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        if (!is_a($value, $this->enumClassName)) {
            return Value::INVALID;
        }

        return $value->name;
    }

    /**
     * @throws JsonException
     */
    public function inputDefinition(): string
    {
        $enumStrings = array_map(
            fn(UnitEnum $enum) => json_encode($enum->name, flags: JSON_THROW_ON_ERROR),
            $this->enumClassName::cases()
        );
        return implode('|', $enumStrings);
    }

    /**
     * @throws JsonException
     */
    public function outputDefinition(): string
    {
        return $this->inputDefinition();
    }

    public function name(): string
    {
        return str_replace('\\', '_', $this->enumClassName);
    }
}
