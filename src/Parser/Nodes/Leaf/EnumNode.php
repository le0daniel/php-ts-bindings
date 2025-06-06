<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;
use UnitEnum;

final readonly class EnumNode implements NodeInterface, LeafType
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

    public function parseValue(mixed $value, $context): UnitEnum|Value
    {
        /** ToDo: Error handling */
        if (!is_string($value)) {
            return Value::INVALID;
        }

        try {
            /** @throws Throwable */
            return $this->enumClassName::{$value};
        } catch (Throwable $exception) {
            return Value::INVALID;
        }
    }

    public function serializeValue(mixed $value, $context): mixed
    {
        if (!is_a($value, $this->enumClassName)) {
            return Value::INVALID;
        }

        return $value->name;
    }
}
