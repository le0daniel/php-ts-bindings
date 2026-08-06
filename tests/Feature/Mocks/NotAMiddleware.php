<?php

declare(strict_types=1);

namespace Tests\Feature\Mocks;

/**
 * Deliberately does NOT implement MiddlewareContract, to prove that a misconfigured middleware
 * surfaces as an RpcError instead of crashing the request.
 */
final class NotAMiddleware
{
}
