<?php declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Exception;

/**
 * Deliberately carries no #[ExposeAs]: the name comes from the middleware's #[Throws(as: ...)].
 */
final class GlobalMiddlewareException extends Exception
{
}
