<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Utils;

final readonly class ArtisanOptions
{
    /**
     * Expands an artisan option into a flat list of names, splitting on commas so that
     * `--with=a,b --with=c` and `--with=a --with=b --with=c` mean the same thing.
     *
     * Takes mixed because that is what Command::option() returns: a repeatable option is an array,
     * a value option a string, a flag a bool, and an absent option null. Anything that is not a
     * string contributes nothing rather than being coerced into a name nobody typed.
     *
     * @return list<string>
     */
    public static function expandOptionsArrayCommaSeparated(mixed $options): array
    {
        $options = match (true) {
            is_array($options) => $options,
            is_string($options) => [$options],
            default => [],
        };

        /** @var list<string> $expanded */
        $expanded = [];
        foreach ($options as $option) {
            if (!is_string($option)) {
                continue;
            }

            foreach (explode(',', $option) as $part) {
                $part = trim($part);
                if ($part !== '' && !in_array($part, $expanded, true)) {
                    $expanded[] = $part;
                }
            }
        }

        return $expanded;
    }

    /**
     * An option that must be a single string. Anything else - a flag, a repeated option, an absent
     * one - is not a value the caller can use, so it comes back as null rather than as "1" or "".
     */
    public static function asString(mixed $option): ?string
    {
        return is_string($option) ? $option : null;
    }
}
