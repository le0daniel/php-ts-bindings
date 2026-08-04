<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Adapters;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\ServerAdapter;
use Psr\Container\ContainerInterface;

final readonly class PsrContainerAdapter implements ServerAdapter
{
    public function __construct(
        private readonly ContainerInterface $container
    )
    {
    }

    public function createMiddleware(string $className): MiddlewareContract
    {
        return $this->container->get($className);
    }

    public function createController(string $className): object
    {
        return $this->container->get($className);
    }
}