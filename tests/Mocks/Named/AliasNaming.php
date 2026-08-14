<?php

declare(strict_types=1);

namespace Tests\Mocks\Named;

use Le0daniel\PhpTsBindings\Data\IO;

/**
 * The reason #[Named] hands the direction to its naming closure: a class with two shapes needs two
 * aliases, and only the closure can tell the directions apart.
 */
final class AliasNaming
{
    public static function perDirection(string $className, IO $io): string
    {
        $base = explode('\\', $className) |> array_last(...);

        return $io === IO::INPUT ? "{$base}Input" : $base;
    }
}
