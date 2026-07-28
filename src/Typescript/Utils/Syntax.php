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
    private const string INDENT = '    ';

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

    public static function stringLiteral(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * The alias a brand is exported under, e.g. `email` => `Email`.
     */
    public static function brandAlias(string $brandName): string
    {
        return ucfirst($brandName);
    }

    /**
     * The full definition of a branded type, e.g. `string & Brand<"email">`.
     */
    public static function branded(string $baseType, string $brandName): string
    {
        return "{$baseType} & Brand<" . self::stringLiteral($brandName) . ">";
    }

    public static function indent(int $level): string
    {
        return str_repeat(self::INDENT, $level);
    }
}
