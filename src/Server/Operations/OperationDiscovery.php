<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use ReflectionClass;
use ReflectionMethod;

final class OperationDiscovery
{
    private const string DEFAULT_NAMESPACE = 'global';

    /** @var array<string, Definition> */
    private(set) array $operations = [];

    /**
     * @param Closure(ReflectionClass<object>, ReflectionMethod, Query|Command): bool|null $filterFn
     */
    public function __construct(private readonly Closure|null $filterFn = null)
    {
    }

    /**
     * The extension point is the $filterFn closure, not a subclass - this class is final. Return
     * false from it to keep an operation out of the registry.
     *
     * @param ReflectionClass<object> $class
     */
    private function filter(ReflectionClass $class, ReflectionMethod $method, Query|Command $attribute): bool
    {
        if ($this->filterFn) {
            return ($this->filterFn)($class, $method, $attribute);
        }

        return true;
    }

    /** @param ReflectionClass<object> $class */
    public function discover(ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes();
            if (empty($attributes)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                if ($attribute->getName() === Query::class || $attribute->getName() === Command::class) {
                    /** @var Query|Command $instance */
                    $instance = $attribute->newInstance();

                    if (!$this->filter($class, $method, $instance)) {
                        continue;
                    }

                    $definition = $this->toDefinition($instance, $class, $method);
                    $fullKey = "{$definition->type->name}@{$definition->fullyQualifiedName()}";

                    if (array_key_exists($fullKey, $this->operations)) {
                        throw new SchemaException("Name collision for: {$definition->fullyQualifiedName()} defined in {$definition->fullyQualifiedClassName} -> {$definition->methodName}.");
                    }

                    $this->operations[$fullKey] = $definition;
                }
            }
        }
    }

    /**
     * @param Query|Command $attribute
     * @param ReflectionClass<object> $class
     * @param ReflectionMethod $method
     * @return Definition
     */
    private function toDefinition(Query|Command $attribute, ReflectionClass $class, ReflectionMethod $method): Definition
    {
        $type = match ($attribute::class) {
            Query::class => OperationType::QUERY,
            Command::class => OperationType::COMMAND,
        };

        $parameters = $method->getParameters();
        if (count($parameters) < 1) {
            throw new SchemaException("Method {$method->name} must have at least one parameter.");
        }

        // Collect all middlewares, on the class and the method itself.
        $middlewareAttributes = [
            ... $class->getAttributes(Middleware::class),
            ... $method->getAttributes(Middleware::class),
        ];

        /** @var list<class-string<MiddlewareContract<mixed>>> $middlewares */
        $middlewares = [];
        foreach ($middlewareAttributes as $middlewareAttribute) {
            array_push($middlewares, ...$middlewareAttribute->newInstance()->middleware);
        }

        return new Definition(
            $type,
            $class->getName(),
            $method->name,
            $attribute->name ?? $method->name,
            $attribute->namespaceAsString() ?? self::DEFAULT_NAMESPACE,
            $middlewares,
        );
    }
}