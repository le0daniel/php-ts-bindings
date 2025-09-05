<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Utils;

final class ArtisanOptions
{
    /**
     * @param string|array<int|string, string>|null $options
     * @return list<string>
     */
    public static function expandOptionsArrayCommaSeparated(string|array|null $options): array
    {
        /** @var array<string> $options */
        $options = match (true) {
            is_array($options) => $options,
            is_string($options) => [$options],
            default => []
        };

        return array_reduce($options, function (array $carry, string $option) {
            $options = array_map(fn(string $option) => trim($option), explode(',', $option));
            $filteredOptions = array_values(array_filter($options, fn(string $option) => !empty($option)));
            return array_values(array_unique([
                ... $carry,
                ... $filteredOptions,
            ]));
        }, []);
    }
}