<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final readonly class Lists
{
    /**
     * @template TValue
     * @param list<TValue|null> $list
     * @return list<TValue>
     */
    public static function filterNullValues(array $list): array
    {
        return array_filter($list, fn($value) => $value !== null) |> array_values(...);
    }
}