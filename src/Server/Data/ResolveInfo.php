<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

final class ResolveInfo
{
    public string $fullyQualifiedName {
        get => "{$this->namespace}.{$this->name}";
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param OperationType $operationType
     * @param class-string<object> $className
     * @param string $methodName
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $name,
        public readonly OperationType $operationType,
        public readonly string $className,
        public readonly string $methodName,
    )
    {
    }
}