<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Tests\Mocks\Errors\RecordMissingException;

/**
 * Declares a valid but unnamed error type: it must not contribute a domain error name.
 */
final class UnnamedTypeMiddleware
{
    #[Throws(RecordMissingException::class, ErrorType::AUTHENTICATION_ERROR)]
    public function handle(): void
    {
    }
}
