<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Utils;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;

/**
 * TypeScript syntax primitives.
 *
 * Everything the generator needs to write is spelled out here, so this package depends on nothing
 * outside itself and CodeGen depends on it rather than the other way round.
 */
final readonly class Syntax
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
        $encoded = self::isValidIdentifier($key)
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
     * A specifier is written verbatim inside a single quoted string literal. Whitespace, quotes and
     * backslashes would either break the literal or silently name a module that does not exist, so
     * they are rejected rather than escaped into something plausible.
     */
    public static function isValidModuleSpecifier(string $specifier): bool
    {
        return $specifier !== '' && preg_match('/[\s\'"\\\\]/', $specifier) !== 1;
    }

    /**
     * A module specifier as it appears after `from`. Single quoted, matching the rest of the
     * generated output — unlike stringLiteral(), which is JSON and therefore double quotes.
     *
     * @throws CodeGenException When the specifier cannot be written verbatim.
     */
    public static function moduleSpecifier(string $specifier): string
    {
        if (!self::isValidModuleSpecifier($specifier)) {
            throw new CodeGenException(
                "'{$specifier}' cannot be written as a TypeScript module specifier."
            );
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
