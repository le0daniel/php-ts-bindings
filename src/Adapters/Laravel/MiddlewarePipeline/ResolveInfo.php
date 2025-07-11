<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline;

final class ResolveInfo
{
    public string $fullyQualifiedName {
        get => "{$this->namespace}.{$this->fullyQualifiedName}";
    }

    public function __construct(
        public readonly string $namespace,
        public readonly string $name,
        public readonly string $operationType,
        public readonly string $className,
        public readonly string $methodName,
    )
    {
    }
}