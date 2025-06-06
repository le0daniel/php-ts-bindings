<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\Literal;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use UnitEnum;

final readonly class LiteralType implements NodeInterface, LeafType
{
    /**
     * @param bool|int|float|null|UnitEnum $value
     */
    public function __construct(
        public Literal $type,
        public mixed  $value,
    )
    {}

    public function __toString(): string
    {
        return match ($this->type) {
            'bool' => $this->value ? 'literal<true>' : 'literal<false>',
            'string' => "literal<'{$this->value}'>",
            default => "literal<{$this->value}>",
        };
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = PHPExport::exportEnumCase($this->type);

        if ($this->type === Literal::ENUM_CASE) {
            $enumCase = PHPExport::exportEnumCase($this->value);
            return "new {$className}({$type}, {$enumCase})";
        }

        $value = var_export($this->value, true);
        return "new {$className}({$type}, {$value})";
    }

    public function parseValue(mixed $value, $context): mixed
    {
        if ($this->type !== Literal::ENUM_CASE) {
            return $value === $this->value ? $this->value : Value::INVALID;
        }

        $name = $this->value->name;
        return $value === $name ? $this->value : Value::INVALID;
    }

    public function serializeValue(mixed $value, $context): mixed
    {
        if ($this->type !== Literal::ENUM_CASE) {
            return $value === $this->value ? $this->value->name : Value::INVALID;
        }

        return $value === $this->value ? $this->value : Value::INVALID;
    }
}