<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum LiteralType: string
{
    case ENUM_CASE = 'enum-case';
    case STRING = 'string';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case NULL = 'null';

    public static function identifyPrimitiveTypeValue(mixed $value): LiteralType
    {
        $nativeGetType = gettype($value);
        return match ($nativeGetType) {
            'double' => LiteralType::FLOAT,
            'integer' => LiteralType::INT,
            'boolean' => LiteralType::BOOL,
            'NULL' => LiteralType::NULL,
            default => throw new \InvalidArgumentException("Unsupported type: {$nativeGetType}"),
        };
    }
}
