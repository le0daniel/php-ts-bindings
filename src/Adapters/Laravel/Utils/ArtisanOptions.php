<?php

declare(strict_types=1);

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
            if (! is_string($option)) {
                continue;
            }

            foreach (explode(',', $option) as $part) {
                $part = trim($part);
                if ($part !== '' && ! in_array($part, $expanded, true)) {
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

    /**
     * An option that must be a positive integer, falling back to a configured default when it was
     * not passed.
     *
     * Absence is read off the value rather than from Command::hasOption(), which is true whenever an
     * option is *declared* and so can never tell you whether the user typed it. Getting that wrong
     * is what made the fallback unreachable and turned a plain `operations:optimize` into a failure.
     *
     * Null means "no usable value", never a silently coerced one: an absent option, a flag, a
     * repeated option, a float and a non-positive number all come back as null so the caller can say
     * so instead of writing a cache with an id length of 0.
     */
    public static function asPositiveInt(mixed $option, mixed $fallback): ?int
    {
        $value = $option ?? $fallback;

        $int = match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => null,
        };

        return $int !== null && $int > 0 ? $int : null;
    }
}
