<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final class Regexes
{
    public static function findFirstVarDeclaration(string $docBlocks): ?string
    {
        $lines = explode(PHP_EOL, $docBlocks);
        foreach ($lines as $line) {
            if (preg_match('/@var\s+(?<type>[^$]+)/', $line, $matches) === 1) {
                return $matches['type'];
            }
        }
        return null;
    }

    public static function findReturnTypeDeclaration(string $docBlocks): ?string
    {
        $lines = explode(PHP_EOL, $docBlocks);
        foreach ($lines as $line) {
            if (preg_match('/@return\s+(?<type>[^$]+)/', $line, $matches) === 1) {
                return $matches['type'];
            }
        }
        return null;
    }

    public static function findParamWithNameDeclaration(string $docBlocks, string $paramName): ?string
    {
        $lines = explode(PHP_EOL, $docBlocks);
        $paramName = preg_quote('$' . $paramName);

        foreach ($lines as $line) {
            if (preg_match("/@param\s+(?<type>[^$]+){$paramName}/", $line, $matches) === 1) {
                return $matches['type'];
            }
        }
        return null;
    }
}