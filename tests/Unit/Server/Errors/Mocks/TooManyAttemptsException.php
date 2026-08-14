<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Exception;

/**
 * A plain exception configured in the rate limited list.
 */
final class TooManyAttemptsException extends Exception
{
}
