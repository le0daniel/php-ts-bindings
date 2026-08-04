<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Le0daniel\PhpTsBindings\Data\IO;

/**
 * A class whose input shape differs from its output shape needs one alias per direction, and only
 * the naming closure knows which direction is being emitted.
 */
final class AliasNaming
{
    public static function perDirection(string $className, IO $io): string
    {
        $base = explode('\\', $className) |> array_last(...);
        return $io === IO::INPUT ? "{$base}Input" : $base;
    }
}
