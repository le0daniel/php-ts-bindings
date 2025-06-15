<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use UnitEnum;

final class PHPExport
{
    public static function absolute(string $className): string
    {
        return str_starts_with($className, '\\') ? $className : '\\' . $className;
    }

    public static function exportEnumCase(UnitEnum $enum): string
    {
        $name = $enum->name;
        $className = self::absolute($enum::class);
        return "{$className}::{$name}";
    }

    /**
     * @param array<int|string, mixed> $array
     * @return string
     */
    public static function exportArray(array $array): string
    {
        if (empty($array)) {
            return '[]';
        }

        if (!array_is_list($array)) {
            throw new \InvalidArgumentException('Array must be a list');
        }

        $imploded = implode(',', array_map(self::export(...), $array));
        return "[{$imploded}]";
    }

    public static function export(mixed $value): string
    {
        if ($value instanceof ExportableToPhpCode) {
            return $value->exportPhpCode();
        }

        if ($value instanceof UnitEnum) {
            return self::exportEnumCase($value);
        }

        if (is_array($value) && array_is_list($value)) {
            $values = implode(', ', array_map(self::export(...), $value));
            return "[{$values}]";
        }

        return var_export($value, true);
    }
}