<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

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
final class NameCheckingMiddleware implements MiddlewareContract
{
    #[Throws(InvalidNameException::class)]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        if (is_array($input) && ($input['name'] ?? null) === 'invalid') {
            throw new InvalidNameException();
        }

        return $next($input);
    }
}
