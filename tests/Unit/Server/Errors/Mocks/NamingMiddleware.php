<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Tests\Mocks\Errors\RecordMissingException;

/**
 * Only reflected, never executed: the #[Throws] attributes on handle are all that matter.
 */
final class NamingMiddleware
{
    #[Throws(RecordMissingException::class, name: 'middleware_name')]
    public function handle(): void
    {
    }
}
