<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Utils;

/**
 * TypeScript syntax primitives.
 *
 * Everything the generator needs to write is spelled out here, so this package depends on nothing
 * outside itself and CodeGen depends on it rather than the other way round.
 */
final class Syntax
{
    public static function isValidIdentifier(string $name): bool
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $name) === 1;
    }

    /**
     * Bare identifiers stay bare; anything else is quoted so it survives as a key.
     */
    public static function objectKey(string $key, bool $optional = false): string
    {
        $encoded = preg_match('/^[a-zA-Z_][a-zA-Z\d_]*$/', $key)
            ? $key
            : self::stringLiteral($key);

        return $optional ? "{$encoded}?" : $encoded;
    }

    /**
     * @throws \JsonException
     */
    public static function stringLiteral(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    public static function wrapInParentheses(string $value): string
    {
        return "({$value})";
    }

    /**
     * A module specifier as it appears after `from`. Single quoted, matching the rest of the
     * generated output — unlike stringLiteral(), which is JSON and therefore double quotes.
     * The specifier is written verbatim, so the caller vouches for it being writable.
     */
    public static function moduleSpecifier(string $specifier): string
    {
        if (str_contains($specifier, "'")) {
            throw new \RuntimeException("Invalid path specified: '{$specifier}'");
        }

        return "'{$specifier}'";
    }

    /**
     * A branded type, e.g. `string & Brand<"email">`.
     */
    public static function branded(string $baseType, string $brandName): string
    {
        return "{$baseType} & Brand<" . self::stringLiteral($brandName) . ">";
    }
}
