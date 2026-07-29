<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Exceptions;

use RuntimeException;

/**
 * Raised when the optimized schema cache cannot serve a key, which always means the cache no
 * longer matches the code that is asking it. Regenerating is the only fix, so the message says so.
 */
final class UnknownTypeKeyException extends RuntimeException
{
    private const string REGENERATE = 'Run: php artisan operations:optimize';

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
