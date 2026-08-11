<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

use Exception;

/**
 * Sits in a configured category list AND is named by the throwing scope's #[Throws]: the scope
 * declaration wins.
 */
final class ConflictException extends Exception
{
}
