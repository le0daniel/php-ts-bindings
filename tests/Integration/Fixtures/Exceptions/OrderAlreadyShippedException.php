<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Exceptions;

use RuntimeException;

/**
 * Mapped to DOMAIN_ERROR via #[Throws(..., name: 'order_already_shipped')] at the operation; the
 * exception itself carries no attributes.
 */
final class OrderAlreadyShippedException extends RuntimeException
{
}
