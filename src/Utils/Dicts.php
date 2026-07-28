<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final readonly class Dicts
{
    /**
     * @template TValue
     * @param array<string, TValue|null> $dict
     * @return array<string, TValue>
     */
    public static function filterNullValues(array $dict): array
    {
        return array_filter($dict, fn($value) => $value !== null);
    }
}