<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class LiteralType implements NodeInterface, LeafType
{
    /**
     * @param 'bool'|'string'|'int'|'float'|'null'|'enum_case' $type
     * @param mixed $value
     */
    public function __construct(
        public string $type,
        public mixed  $value,
    )
    {
        if (!in_array($this->type, ['bool', 'string', 'int', 'float', 'null', 'enum_case'])) {
            throw new InvalidArgumentException("Unsupported type: {$this->type}");
        }
    }

    public static function identifyPrimitiveTypeValue(mixed $value): string
    {
        $nativeGetType = gettype($value);
        return match ($nativeGetType) {
            'double' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'NULL' => 'null',
            default => throw new InvalidArgumentException("Unsupported type: {$nativeGetType}"),
        };
    }

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
        $type = var_export($this->type, true);
        $value = var_export($this->value, true);
        return "new {$className}({$type}, {$value})";
    }

    public function parseValue(mixed $value, $context): mixed
    {
        if ($this->type !== 'enum_case') {
            return $value === $this->value ? $this->value : Value::INVALID;
        }

        $name = $this->value->name;
        return $value === $name ? $this->value : Value::INVALID;
    }

    public function serializeValue(mixed $value, $context): mixed
    {
        if ($this->type !== 'enum_case') {
            return $value === $this->value ? $this->value->name : Value::INVALID;
        }

        return $value === $this->value ? $this->value : Value::INVALID;
    }
}