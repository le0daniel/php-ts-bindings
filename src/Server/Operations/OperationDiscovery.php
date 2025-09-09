<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Contracts\Discoverer;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class OperationDiscovery implements Discoverer
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
     * Used for extensibility.
     * Return false to filter the item out and have your own custom rules
     *
     * @param ReflectionClass<object> $class
     * @param ReflectionMethod $method
     * @param Query|Command $attribute
     * @return bool
     */
    protected function filter(ReflectionClass $class, ReflectionMethod $method, Query|Command $attribute): bool
    {
        if ($this->filterFn) {
            return ($this->filterFn)($class, $method, $attribute);
        }

        return true;
    }

    /** @param ReflectionClass<object> $class */
    final public function discover(ReflectionClass $class): void
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
                        throw new RuntimeException("Name collision for: {$definition->fullyQualifiedName()} defined in {$definition->fullyQualifiedClassName} -> {$definition->methodName}.");
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
            throw new RuntimeException("Method {$method->name} must have at least one parameter.");
        }

        $attributes = [
            // Collect all middlewares, on the class and the method itself.
            ... $class->getAttributes(Middleware::class),
            ... $method->getAttributes(Middleware::class),
        ];

        $middlewares = empty($attributes) ? [] : array_reduce($attributes, function (array $carry, ReflectionAttribute $attribute) {
            $instance = $attribute->newInstance();
            array_push($carry, ...$instance->middleware);
            return $carry;
        }, []);

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