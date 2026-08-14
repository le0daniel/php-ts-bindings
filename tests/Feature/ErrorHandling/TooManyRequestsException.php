<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

use Exception;

/**
 * A plain exception the scenarios list as rate limited or map via a scope declaration.
 */
final class TooManyRequestsException extends Exception
{
}
