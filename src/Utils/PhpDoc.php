<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final class PhpDoc
{
    private const array REGEX_PARTS = [
        '{cn}' => '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*',
        '{fqcn}' => '\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*',
    ];
    private const string LOCAL_TYPE_REGEX = "/@phpstan-type\s+(?<typeName>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s+(?<typeDefinition>[^@]+)/m";
    private const string IMPORTED_TYPE_REGEX = '/@phpstan-import-type\s+(?<typeName>{cn})\s+from\s+(?<fromClass>{fqcn})(\s+as\s+(?<alias>{cn}))?/';
    private const string GENERICS_TYPE_REGEX = '/@template(-covariant)?\s+(?<genericName>{cn})/';

    public static function normalize(string $docBlocks): string
    {
        return preg_replace('/(^\s*\/\*\*)|(^\s+\*\s)|\s\*\/\s*$/m', '', $docBlocks) ?? $docBlocks;
    }

    private static function compileRegex(string $regex): string
    {
        return str_replace(array_keys(self::REGEX_PARTS), array_values(self::REGEX_PARTS), $regex);
    }

    /**
     * @param false|string|null $docBlock
     * @return array<string, array{className: string, typeName: string}>
     */
    public static function findImportedTypeDefinition(null|false|string $docBlock): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $importedTypes = [];
        foreach (explode(PHP_EOL, $docBlock) as $line) {
            $matches = [];
            if (preg_match(self::compileRegex(self::IMPORTED_TYPE_REGEX), $line, $matches) !== 1) {
                continue;
            }

            $importedTypeName = $matches['typeName'];
            $localAlias = $matches['alias'] ?? null;
            $importedTypes[$localAlias ?? $importedTypeName] = [
                'className' => $matches['fromClass'],
                'typeName' => $importedTypeName,
            ];
        }
        return $importedTypes;
    }

    /**
     * @param false|string|null $docBlock
     * @return list<string>
     */
    public static function findGenerics(null|false|string $docBlock): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $matches = [];
        $result = preg_match_all(
            self::compileRegex(self::GENERICS_TYPE_REGEX),
            $docBlock,
            $matches,
            PREG_SET_ORDER
        );

        if (!$result) {
            return [];
        }

        return array_map(fn(array $match): string => $match['genericName'], $matches);
    }

    /** @return array<string,string> */
    public static function findLocallyDefinedTypes(null|false|string $docBlock): array
    {
        if (empty($docBlock)) {
            return [];
        }

        $matches = [];
        $result = preg_match_all(
            self::compileRegex(self::LOCAL_TYPE_REGEX),
            self::normalize($docBlock),
            $matches,
            PREG_SET_ORDER
        );

        if (!$result) {
            return [];
        }

        $localTypes = [];
        foreach ($matches as $match) {
            $localTypes[$match['typeName']] = trim(str_replace(PHP_EOL, ' ', $match['typeDefinition']));
        }

        return $localTypes;
    }
}