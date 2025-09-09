<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

use JsonException;

final class Typescript
{
    /**
     * @throws JsonException
     */
    public static function literalUnion(string|int|bool|null ...$values): string
    {
        return implode('|', array_map( fn(string|int|bool|null $value): string => match (true) {
            is_string($value) => json_encode($value, JSON_THROW_ON_ERROR),
            is_int($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
        }, $values));
    }
}