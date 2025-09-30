<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Closure;

final class Arrays
{
    /**
     * @template TArrayKey of array-key
     * @template TArrayValue
     * @template TValue
     *
     * @param array<TArrayKey, TArrayValue> $array
     * @param Closure(TArrayKey, TArrayValue): TValue $callback
     * @return array<TArrayKey, TValue>
     */
    public static function mapWithKeys(array $array, Closure $callback): array
    {
        $mapped = [];
        foreach ($array as $key => $value) {
            $mapped[$key] = $callback($key, $value);
        }
        return $mapped;
    }

    /**
     * @template TKey
     * @template TValue
     * @param array<TKey, TValue|null> $array
     * @return array<TKey, TValue>
     */
    public static function filterNullValues(array $array): array
    {
        $result = array_filter($array, fn($value) => $value !== null);
        if (array_is_list($array)) {
            return array_values($result);
        }
        return $result;
    }
}