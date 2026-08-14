<?php

declare(strict_types=1);

namespace Tests\Feature\Mocks;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ConfigurableMiddleware;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

/**
 * A ConfigurableMiddleware written the way the contract discourages: configure() mutates $this and
 * returns it. The server clones before calling configure(), so even this mistake must never leak
 * config into a container-shared instance.
 *
 * @implements ConfigurableMiddleware<mixed>
 */
final class MutatingPrefixMiddleware implements ConfigurableMiddleware
{
    public string $prefix = '';

    public function configure(array $config): static
    {
        $this->prefix = (string) ($config['prefix'] ?? $this->prefix);

        return $this;
    }

    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        if (is_array($input) && is_string($input['name'] ?? null)) {
            $input['name'] = $this->prefix.$input['name'];
        }

        return $next($input);
    }
}
