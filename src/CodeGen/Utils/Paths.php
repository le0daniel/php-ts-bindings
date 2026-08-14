<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

/**
 * Where the generated files sit relative to each other. The tree is two levels — lib files under
 * lib/, operation modules at the output root — and both halves of the rule live here.
 */
final readonly class Paths
{
    private const string LIB_PREFIX = './lib/';

    public static function relative(string $path): string
    {
        return "./{$path}";
    }

    /**
     * How a lib file is named, always: an emitter has no idea where its own output lands, so it
     * writes the specifier a module at the output root would.
     */
    public static function libImport(string $name): string
    {
        return self::relative("lib/{$name}");
    }

    /**
     * The same module named from inside lib/, where a sibling is reached directly. Only the
     * orchestrator knows which files landed there, so it is the only caller.
     */
    public static function fromInsideLib(string $specifier): string
    {
        return str_starts_with($specifier, self::LIB_PREFIX)
            ? self::relative(substr($specifier, strlen(self::LIB_PREFIX)))
            : $specifier;
    }
}
