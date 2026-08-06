<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Closure;

final readonly class Arrays
{
    /**
     * @template TArrayKey of array-key
     * @template TArrayValue
     * @template TValue
     *
     * @param  array<TArrayKey, TArrayValue>  $array
     * @param  Closure(TArrayKey, TArrayValue): TValue  $callback
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
}
