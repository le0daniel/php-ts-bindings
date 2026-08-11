<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * Declares and throws from the same scope: the one middleware case that does yield a domain error.
 *
 * @implements MiddlewareContract<mixed>
 */
final class SelfDeclaringMiddleware implements MiddlewareContract
{
    #[Throws(MiddlewareOwnException::class, name: 'middleware_own_error')]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        throw new MiddlewareOwnException();
    }
}
