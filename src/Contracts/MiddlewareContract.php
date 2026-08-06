<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Closure;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * A ring of the onion that every operation is executed in.
 *
 * INVARIANT: handle() always yields an RpcSuccess or an RpcError, never a Throwable. If a
 * middleware (or anything below it) throws, the pipeline converts the Throwable into an RpcError
 * at the ring where it happened and returns it as the value of the enclosing $next() call. A
 * middleware therefore never has to catch anything to keep its post-processing running - but it
 * must be prepared for $next() to hand back an RpcError.
 *
 * @phpstan-type Next Closure(mixed): (RpcSuccess|RpcError)
 *
 * @template-contravariant TContext = mixed
 */
interface MiddlewareContract
{
    /**
     * @param  Next  $next
     * @param  TContext  $context
     */
    public function handle(
        mixed $input,
        Closure $next,
        mixed $context,
        ResolveInfo $info,
        Client $client,
    ): RpcSuccess|RpcError;
}
