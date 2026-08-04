<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Middleware;

use Closure;
use Illuminate\Container\Attributes\Config;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Override;

/**
 * @implements MiddlewareContract<mixed>
 */
final readonly class LocalMetadataMiddleware implements MiddlewareContract
{
    public function __construct(
        #[Config('app.debug')] private bool $isDebuggingEnabled
    )
    {
    }

    #[Override]
    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        if (!$this->isDebuggingEnabled) {
            return $next($input);
        }

        $startTime = microtime(true);
        $result = $next($input);
        $durationMs = (int)ceil((microtime(true) - $startTime) * 1000);

        return $result->appendMetadata([
            'fullyQualifiedHandler' => "{$info->className}@{$info->methodName}",
            'durationMs' => $durationMs,
            'client' => [
                'class' => $client::class,
            ],
            'info' => [
                'namespace' => $info->namespace,
                'name' => $info->name,
                'fqn' => $info->fullyQualifiedName,
                'operationType' => $info->operationType->name,
            ],
            'handler' => [
                'className' => $info->className,
                'methodName' => $info->methodName,
            ],
            'middleware' => $info->middleware,
            'input' => $input,
            'context' => [
                'class' => is_object($context) ? get_class($context) : gettype($context),
            ],
        ]);
    }

}