<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Middleware;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

final class LocalMetadataMiddleware
{
    /**
     * @param mixed $input
     * @param Closure(mixed): (RpcSuccess|RpcError) $next
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @param Client $client
     * @return RpcSuccess|RpcError
     */
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $resolveInfo, Client $client): RpcSuccess|RpcError
    {
        if (config('app.debug') !== true) {
            return $next($input);
        }

        $startTime = microtime(true);
        $result = $next($input);
        $durationMs = (int)ceil((microtime(true) - $startTime) * 1000);

        return $result->appendMetadata([
            'durationMs' => $durationMs,
            'client' => [
                'class' => $client::class,
            ],
            'info' => [
                'namespace' => $resolveInfo->namespace,
                'name' => $resolveInfo->name,
                'fqn' => $resolveInfo->fullyQualifiedName,
                'operationType' => $resolveInfo->operationType->name,
            ],
            'handler' => [
                'className' => $resolveInfo->className,
                'methodName' => $resolveInfo->methodName,
            ],
            'middleware' => $resolveInfo->middleware,
            'input' => $input,
            'context' => [
                'class' => is_object($context) ? get_class($context) : gettype($context),
            ],
        ]);
    }

}