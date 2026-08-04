<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Data\Exceptions;

/**
 * Raised when the optimized schema cache cannot serve a key, which always means the cache no
 * longer matches the code that is asking it. Regenerating is the only fix, so the message says so.
 */
final class UnknownTypeKeyException extends ParserException
{
    /**
     * Deliberately names the concept rather than a command: the optimizer is usable without any
     * framework, and telling one of those users to run artisan is telling them to run nothing.
     * An adapter that has a command for this appends its own.
     */
    private const string REGENERATE = 'Regenerate the optimized schema cache.';

    public static function forKey(string $key): self
    {
        return new self(
            "Unknown schema key '{$key}'. The optimized schema cache is stale or was written by a "
            . 'different build. ' . self::REGENERATE,
        );
    }

    public static function forLegacyCacheShape(): self
    {
        return new self(
            'The optimized schema cache uses a format written before schema identity was fixed. '
            . 'Caches in that format can silently merge schemas that differ only in their '
            . 'constraints, dropping validation. ' . self::REGENERATE,
        );
    }
}
