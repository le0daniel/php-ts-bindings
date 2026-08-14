<?php

declare(strict_types=1);

namespace Tests\Mocks\Errors;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * Names every exception it declares, including two that ErrorOperations also declares: the probe
 * for which declaration wins.
 *
 * @implements MiddlewareContract<mixed>
 */
final class RenamingMiddleware implements MiddlewareContract
{
    #[Throws(MiddlewareDomainException::class, name: 'renamed_middleware_failure')]
    #[Throws(ExposedDomainException::class, name: 'middleware_name')]
    #[Throws(UnexposedException::class, name: 'middleware_named_it')]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        return $next($input);
    }
}
