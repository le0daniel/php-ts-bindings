<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

use Exception;

/**
 * Declared in one scope and thrown from another, which is exactly what must not become a domain
 * error: a declaration covers throws from its own scope only.
 */
final class SharedException extends Exception
{
}
