<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use NoDiscard;

final readonly class Dicts
{
    /**
     * @template TValue
     * @param array<string, TValue|null> $dict
     * @return array<string, TValue>
     */
    #[NoDiscard]
    public static function filterNullValues(array $dict): array
    {
        return array_filter($dict, fn($value) => $value !== null);
    }
}