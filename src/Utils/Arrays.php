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
}