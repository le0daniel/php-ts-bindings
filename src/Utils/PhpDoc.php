<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final class PhpDoc
{
    public static function normalize(string $docBlocks): string
    {
        return preg_replace('/(^\s*\/\*\*)|(^\s+\*\s)|\s\*\/\s*$/m', '', $docBlocks) ?? $docBlocks;
    }
}