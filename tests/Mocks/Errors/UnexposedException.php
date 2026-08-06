<?php

declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Exception;

/**
 * Declared via #[Throws], but carries no #[ExposeAs]: it must not become a domain error.
 */
final class UnexposedException extends Exception
{
}
