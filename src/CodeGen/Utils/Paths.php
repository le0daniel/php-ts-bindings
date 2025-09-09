<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

final class Paths
{
    public static function libImport(string $name): string
    {
        return "./lib/{$name}";
    }
}