<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Tests\Mocks\Errors\UnexposedException;

/**
 * Declares the same name as ThrowResolverOperations::declaresNamedDomainError to pin deduplication.
 */
final class DuplicateNamingMiddleware
{
    #[Throws(UnexposedException::class, name: 'direct_name')]
    public function handle(): void
    {
    }
}
