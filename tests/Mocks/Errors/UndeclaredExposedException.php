<?php

declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Exception;
use Le0daniel\PhpTsBindings\Contracts\Attributes\ExposeAs;

/**
 * Carries #[ExposeAs], but no operation declares it via #[Throws]: it must not become a domain error.
 */
#[ExposeAs('undeclared_failure')]
final class UndeclaredExposedException extends Exception
{
}
