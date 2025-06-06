<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

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
}