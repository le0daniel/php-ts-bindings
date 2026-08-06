<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;
use UnitEnum;

final class EnumNode implements LeafNode, NodeInterface
{
    /** @var array<string, UnitEnum> */
    private array $cases;

    /**
     * @param  class-string<UnitEnum>  $enumClassName
     */
    public function __construct(
        public readonly string $enumClassName,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return "enum<{$this->enumClassName}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $enumClass = PHPExport::absolute($this->enumClassName);
        $className = PHPExport::absolute(self::class);

        return "new {$className}({$enumClass}::class)";
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): UnitEnum|Value
    {
        /** ToDo: Error handling */
        if (! is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected string name of enum {$this->enumClassName}, got: ".gettype($value),
                    'value' => $value,
                ]
            ));

            return Value::INVALID;
        }

        $cases = $this->cases ??= array_column(
            $this->enumClassName::cases(),
            null,
            'name',
        );

        if (isset($cases[$value])) {
            return $cases[$value];
        }

        $context->addIssue(new Issue(
            IssueMessage::INVALID_TYPE,
            [
                'message' => "Expected string name of enum {$this->enumClassName}, got: '{$value}'",
                'value' => $value,
            ]
        ));

        return Value::INVALID;
    }

    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): mixed
    {
        if (! is_a($value, $this->enumClassName)) {
            $context->addIssue(Issue::invalidType($this->enumClassName, $value));

            return Value::INVALID;
        }

        return $value->name;
    }

    public function name(): string
    {
        return str_replace('\\', '_', $this->enumClassName);
    }
}
