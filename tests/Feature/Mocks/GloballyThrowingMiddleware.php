<?php

declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * Registered through ServerConfiguration::withMiddlewares() rather than #[Middleware], which is
 * the case that never reached ExposedExceptions.
 *
 * @implements MiddlewareContract<mixed>
 */
final class GloballyThrowingMiddleware implements MiddlewareContract
{
    #[Throws(GlobalMiddlewareException::class, name: 'global_middleware_failed')]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        if (is_array($input) && ($input['name'] ?? null) === 'global-boom') {
            throw new GlobalMiddlewareException();
        }

        return $next($input);
    }
}
