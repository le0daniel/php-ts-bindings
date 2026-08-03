<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

final readonly class Paths
{
    public static function relative(string $path): string
    {
        return "./{$path}";
    }

    public static function libImport(string $name): string
    {
        return self::relative("lib/{$name}");
    }
}