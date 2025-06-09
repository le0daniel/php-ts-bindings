<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;

readonly class BuiltInNode implements NodeInterface, LeafNode
{

    public function __construct(public BuiltInType $type)
    {
    }

    public static function is(string $type): bool
    {
        return in_array($type, ['string', 'int', 'bool', 'null', 'float', 'mixed'], true);
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

    public function parseValue(mixed $value, $context): mixed
    {
        return match ($this->type) {
            BuiltInType::STRING => is_string($value) ? $value : Value::INVALID,
            BuiltInType::INT => is_int($value) ? $value : Value::INVALID,
            BuiltInType::BOOL => is_bool($value) ? $value : Value::INVALID,
            BuiltInType::NULL => is_null($value) ? $value : Value::INVALID,
            BuiltInType::FLOAT => is_float($value) || is_int($value) ? $value : Value::INVALID,
            BuiltInType::MIXED => $value,
        };
    }

    public function serializeValue(mixed $value, $context): mixed
    {
        try {
            return match ($this->type) {
                BuiltInType::STRING => (string) $value,
                BuiltInType::INT => (int) $value,
                BuiltInType::BOOL => (bool) $value,
                BuiltInType::NULL => null,
                BuiltInType::FLOAT => (float) $value,
                BuiltInType::MIXED => $value,
            };
        } catch (Throwable) {
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