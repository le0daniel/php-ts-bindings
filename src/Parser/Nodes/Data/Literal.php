<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum Literal: string
{
    case ENUM_CASE = 'enum-case';
    case STRING = 'string';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case NULL = 'null';

    public static function identifyPrimitiveTypeValue(mixed $value): Literal
    {
        $nativeGetType = gettype($value);
        return match ($nativeGetType) {
            'double' => Literal::FLOAT,
            'integer' => Literal::INT,
            'boolean' => Literal::BOOL,
            'NULL' => Literal::NULL,
            default => throw new \InvalidArgumentException("Unsupported type: {$nativeGetType}"),
        };
    }
}
