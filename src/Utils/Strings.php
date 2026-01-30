<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Stringable;
use UnitEnum;

final class Strings
{
    /**
     * @param class-string $className
     * @return string
     */
    public static function classBaseName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    public static function toString(UnitEnum|string|Stringable $value): string
    {
        if ($value instanceof UnitEnum) {
            return match(true) {
                $value instanceof \BackedEnum => (string) $value->value,
                default => $value->name,
            };
        }

        return (string) $value;
    }
}