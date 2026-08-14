<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ConfigurableMiddleware;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * @implements ConfigurableMiddleware<mixed>
 */
final readonly class PrefixNameMiddleware implements ConfigurableMiddleware
{
    public function __construct(public string $prefix = '')
    {
    }

    public function configure(array $config): static
    {
        return clone($this, ['prefix' => (string) ($config['prefix'] ?? $this->prefix)]);
    }

    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        if (is_array($input) && is_string($input['name'] ?? null)) {
            $input['name'] = $this->prefix.$input['name'];
        }

        return $next($input);
    }
}
