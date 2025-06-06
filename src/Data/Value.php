<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Data;

enum Value
{
    case INVALID;
    case UNDEFINED;

    public function asPhpValue(): null
    {
        return null;
    }

    public static function toNull(mixed $value): mixed
    {
        return $value instanceof Value ? null : $value;
    }
}
