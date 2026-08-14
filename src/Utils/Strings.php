<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use UnitEnum;

final readonly class Strings
{
    /**
     * The last segment of a backslash separated name. Not restricted to class-string: it is also
     * used on namespaces and on names parsed out of `use` statements, which are unverified.
     */
    public static function classBaseName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }

    public static function toString(UnitEnum|string|\Stringable $value): string
    {
        if ($value instanceof UnitEnum) {
            return match (true) {
                $value instanceof \BackedEnum => (string) $value->value,
                default => $value->name,
            };
        }

        return (string) $value;
    }
}
