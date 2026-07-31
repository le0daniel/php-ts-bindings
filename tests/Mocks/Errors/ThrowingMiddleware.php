<?php declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * @implements MiddlewareContract<mixed>
 */
final class ThrowingMiddleware implements MiddlewareContract
{
    #[Throws(MiddlewareDomainException::class)]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        return $next($input);
    }
}
