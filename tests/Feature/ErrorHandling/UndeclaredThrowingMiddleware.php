<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorHandling;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * Throws SharedException without declaring it. The handler behind it does declare it - which must
 * not matter, because the handler is not the scope this throw came from.
 *
 * @implements MiddlewareContract<mixed>
 */
final class UndeclaredThrowingMiddleware implements MiddlewareContract
{
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        throw new SharedException();
    }
}
