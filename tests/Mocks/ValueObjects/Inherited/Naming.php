<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

/**
 * Naming strategies referenced from attributes with first-class callable syntax. PHP rejects a
 * closure literal in an attribute argument, so the closure has to point at a named function or
 * static method like these.
 */
final class Naming
{
    public static function suffixedAlias(string $className): string
    {
        return self::baseName($className).'Alias';
    }

    /**
     * Paired with contractAlias() so a test can tell which of two competing declarations won.
     */
    public static function parentAlias(string $className): string
    {
        return self::baseName($className).'FromParent';
    }

    public static function contractAlias(string $className): string
    {
        return self::baseName($className).'FromContract';
    }

    public static function prefixedBrand(string $className): string
    {
        return 'app'.self::baseName($className);
    }

    public static function invalidIdentifier(string $className): string
    {
        return self::baseName($className).'-not-an-identifier';
    }

    /**
     * Deliberately untyped: pins the guard that a closure must return a string.
     */
    public static function notAString(string $className): mixed
    {
        return strlen($className);
    }

    private static function baseName(string $className): string
    {
        return explode('\\', $className) |> array_last(...);
    }
}
