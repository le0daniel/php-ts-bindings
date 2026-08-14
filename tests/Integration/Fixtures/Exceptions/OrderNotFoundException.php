<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Exceptions;

use RuntimeException;

/**
 * Mapped to NOT_FOUND via #[Throws(..., type: ErrorType::NOT_FOUND)] at the operation.
 */
final class OrderNotFoundException extends RuntimeException
{
}
