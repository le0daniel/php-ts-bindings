<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

/**
 * A subclass of a configured exception: the lists match instanceof, so this stays a 401 without
 * being listed itself.
 */
final class TokenExpiredException extends SessionExpiredException
{
}
