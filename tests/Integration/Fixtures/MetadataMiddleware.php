<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ConfigurableMiddleware;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;

final class MetadataMiddleware implements ConfigurableMiddleware
{
    private string $value = 'default1';

    public function handle(mixed $input, Closure $next, mixed $context, ResolveInfo $info, Client $client): RpcSuccess|RpcError
    {
        /** @var RpcSuccess|RpcError $result */
        $result = $next($input);
        return $result->appendMetadata(['key' => $this->value]);
    }

    public function configure(array $config): self
    {
        $this->value = $config['value'] ?? 'default';
        return $this;
    }
}
