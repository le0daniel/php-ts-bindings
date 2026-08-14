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
 * Declares SharedException but never throws it. When the handler throws that exception instead,
 * this declaration must not name it - it belongs to this scope, and this scope stayed quiet.
 *
 * @implements MiddlewareContract<mixed>
 */
final class DecoyDeclaringMiddleware implements MiddlewareContract
{
    #[Throws(SharedException::class, name: 'shared_from_middleware')]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        return $next($input);
    }
}
