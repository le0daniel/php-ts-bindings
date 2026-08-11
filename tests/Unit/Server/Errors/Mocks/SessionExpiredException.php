<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Errors\Mocks;

use Exception;

/**
 * Matches through the configured RequiresLoginInterface, not by its own class name.
 */
final class SessionExpiredException extends Exception implements RequiresLoginInterface
{
}
