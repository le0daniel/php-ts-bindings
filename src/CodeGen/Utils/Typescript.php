<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

use JsonException;
use Le0daniel\PhpTsBindings\Utils\Regexes;

final class Typescript
{
    public static function objectKey(string $key, bool $optional = false): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z\d_]*$/', $key)) {
            return $optional ? "{$key}?" : $key;
        }
        return json_encode($key, JSON_THROW_ON_ERROR) . ($optional ? '?' : '');
    }
}