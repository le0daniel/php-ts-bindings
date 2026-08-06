<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use NoDiscard;

final readonly class Lists
{
    /**
     * @template TValue
     *
     * @param  list<TValue|null>  $list
     * @return list<TValue>
     */
    #[NoDiscard]
    public static function filterNullValues(array $list): array
    {
        return array_filter($list, fn ($value) => $value !== null) |> array_values(...);
    }

    /**
     * array_unique preserves keys, which breaks the list. This does not.
     *
     * @template TValue of int|string
     *
     * @param  list<TValue>  $list
     * @return list<TValue>
     */
    #[NoDiscard]
    public static function unique(array $list): array
    {
        return array_unique($list) |> array_values(...);
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    #[NoDiscard]
    public static function sorted(array $list): array
    {
        usort($list, strcmp(...));

        return $list;
    }
}
