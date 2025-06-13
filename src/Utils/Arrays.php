<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Closure;

final class Arrays
{
    public static function mapWithKeys(array $array, Closure $callback): array
    {
        $mapped = [];
        foreach ($array as $key => $value) {
            $mapped[$key] = $callback($key, $value);
        }
        return $mapped;
    }

    /**
     * @template T of array
     * @param T $array
     * @return T
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