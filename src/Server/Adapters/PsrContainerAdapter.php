<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Adapters;

use Le0daniel\PhpTsBindings\Contracts\ServerExecutionAdapter;
use Psr\Container\ContainerInterface;

final readonly class PsrContainerAdapter implements ServerExecutionAdapter
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function createMiddleware(string $className): object
    {
        return $this->container->get($className);
    }

    public function createOperationClass(string $className): object
    {
        return $this->container->get($className);
    }
}