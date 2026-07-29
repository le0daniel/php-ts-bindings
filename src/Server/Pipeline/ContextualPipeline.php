<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Pipeline;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Throwable;

/**
 * Runs the middlewares as an onion around the destination, first middleware outermost.
 *
 * INVARIANT: nothing escapes this pipeline as a Throwable. Every ring - and the destination -
 * is wrapped, so a failure is turned into an RpcError right where it happened and handed back to
 * the enclosing middleware as the return value of its $next() call. The stack is never unwound
 * past a middleware, which means outer rings always get to run their post-processing on the error.
 *
 * The conversion goes through $onError, so failures are presented the same way whether they come
 * from a middleware or from the operation itself. If $onError fails too there is nobody left to
 * ask, so the pipeline falls back to a bare INTERNAL_ERROR rather than letting the request crash.
 *
 * @phpstan-import-type Next from MiddlewareContract
 * @template-contravariant TContext = mixed
 */
final readonly class ContextualPipeline
{
    /**
     * @param list<MiddlewareContract<TContext>> $middlewares
     * @param Closure(Throwable): RpcError $onError
     * @param Closure(mixed): (RpcSuccess|RpcError) $destination
     */
    public function __construct(
        private array   $middlewares,
        private Closure $onError,
        private Closure $destination,
    )
    {
    }

    /**
     * @param TContext $context
     */
    public function execute(mixed $input, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        $next = function (mixed $input) use ($info): RpcSuccess|RpcError {
            try {
                return ($this->destination)($input);
            } catch (Throwable $throwable) {
                return $this->toRpcError($throwable, $info);
            }
        };

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = $this->ring($middleware, $next, $context, $info, $client);
        }

        return $next($input);
    }

    /**
     * @param MiddlewareContract<TContext> $middleware
     * @param Next $next
     * @param TContext $context
     * @return Next
     */
    private function ring(MiddlewareContract $middleware, Closure $next, mixed $context, ResolveInfo $info, Client $client): Closure
    {
        return function (mixed $input) use ($middleware, $next, $context, $info, $client): RpcSuccess|RpcError {
            try {
                return $middleware->handle($input, $next, $context, $info, $client);
            } catch (Throwable $throwable) {
                return $this->toRpcError($throwable, $info);
            }
        };
    }

    private function toRpcError(Throwable $throwable, ResolveInfo $info): RpcError
    {
        try {
            return ($this->onError)($throwable);
        } catch (Throwable $failedToPresent) {
            return new RpcError(
                ErrorType::INTERNAL_ERROR,
                $failedToPresent,
                ['type' => 'INTERNAL_SERVER_ERROR'],
                $info,
            );
        }
    }
}
