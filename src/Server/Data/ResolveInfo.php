<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;

final class ResolveInfo
{
    public string $fullyQualifiedName {
        get => "{$this->namespace}.{$this->name}";
    }

    /**
     * @param  class-string<object>  $className
     * @param  list<class-string<MiddlewareContract<mixed>>>  $middleware
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $name,
        public readonly OperationType $operationType,
        public readonly string $className,
        public readonly string $methodName,
        public readonly array $middleware,
    ) {
    }
}
