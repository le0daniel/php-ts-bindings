<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;

readonly class BuiltInType implements NodeInterface, LeafType
{

    public function __construct(public string $type)
    {
        if (!self::is($this->type)) {
            throw new InvalidArgumentException("Invalid primitive type: {$this->type}");
        }
    }

    public static function is(string $type): bool
    {
        return in_array($type, ['string', 'int', 'bool', 'null', 'float', 'mixed'], true);
    }

    public function __toString(): string
    {
        return $this->type;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(BuiltInType::class);
        $type = var_export($this->type, true);

        return implode('', [
            "new {$className}($type)"
        ]);
    }

    public function parseValue(mixed $value, $context): mixed
    {
        return match ($this->type) {
            "string" => is_string($value) ? $value : Value::INVALID,
            "int" => is_int($value) ? $value : Value::INVALID,
            "bool" => is_bool($value) ? $value : Value::INVALID,
            "null" => is_null($value) ? $value : Value::INVALID,
            "float" => is_float($value) || is_int($value) ? $value : Value::INVALID,
            "mixed" => $value,
        };
    }

    public function serializeValue(mixed $value, $context): mixed
    {
        try {
            return match ($this->type) {
                "string" => (string) $value,
                "int" => (int) $value,
                "bool" => (bool) $value,
                "null" => null,
                "float" => (float) $value,
                "mixed" => $value,
            };
        } catch (Throwable) {
            return Value::INVALID;
        }
    }
}