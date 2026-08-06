<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use UnitEnum;

final readonly class PHPExport
{
    /**
     * Writes through a temporary file in the same directory and renames it into place.
     *
     * The generated caches this produces are require()d while the application is serving traffic,
     * so a half written file would be loaded as valid PHP and fail far from here. rename() is
     * atomic within a filesystem, which makes a reader see either the whole old file or the whole
     * new one - hence the temporary alongside the target rather than in the system temp directory,
     * which may be a different filesystem.
     *
     * @throws ParserException
     */
    public static function writeFileAtomically(string $filePath, string $contents): void
    {
        $directory = dirname($filePath);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new ParserException("Failed to write file to {$filePath}: {$directory} is not a writable directory.");
        }

        $temporaryPath = $filePath.'.'.getmypid().'.tmp';

        if (file_put_contents($temporaryPath, $contents) !== strlen($contents)) {
            @unlink($temporaryPath);
            throw new ParserException("Failed to write file to {$filePath}");
        }

        if (! @rename($temporaryPath, $filePath)) {
            @unlink($temporaryPath);
            throw new ParserException("Failed to write file to {$filePath}");
        }
    }

    public static function absolute(string $className): string
    {
        return str_starts_with($className, '\\') ? $className : '\\'.$className;
    }

    public static function exportEnumCase(UnitEnum $enum): string
    {
        $name = $enum->name;
        $className = self::absolute($enum::class);

        return "{$className}::{$name}";
    }

    /**
     * @param  array<int|string, mixed>  $array
     */
    public static function exportArray(array $array): string
    {
        if (count($array) === 0) {
            return '[]';
        }

        if (! array_is_list($array)) {
            throw new ParserException('Array must be a list');
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
