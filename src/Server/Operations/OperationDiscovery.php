<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Le0daniel\PhpTsBindings\Contracts\Discoverer;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class OperationDiscovery implements Discoverer
{
    private const string DEFAULT_NAMESPACE = 'global';

    /** @var array<string, Definition> */
    private(set) array $queries = [];

    /** @var array<string, Definition> */
    private(set) array $commands = [];

    /** @param ReflectionClass<object> $class */
    public function discover(ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes();
            if (empty($attributes)) {
                continue;
            }

            foreach ($attributes as $attribute) {
                if ($attribute->getName() === Query::class) {
                    /** @var Query $instance */
                    $instance = $attribute->newInstance();
                    [$fqn, $definition] = $this->toDefinition($instance, $class, $method);
                    if (array_key_exists($fqn, $this->queries)) {
                        throw new RuntimeException("Name collision for query: {$fqn} defined in {$definition->fullyQualifiedClassName} -> {$definition->methodName}.");
                    }
                    $this->queries[$fqn] = $definition;
                    break;
                }

                if ($attribute->getName() === Command::class) {
                    /** @var Command $instance */
                    $instance = $attribute->newInstance();
                    [$fqn, $definition] = $this->toDefinition($instance, $class, $method);
                    if (array_key_exists($fqn, $this->commands)) {
                        throw new RuntimeException("Name collision for action: {$fqn} defined in {$definition->fullyQualifiedClassName} -> {$definition->methodName}.");
                    }
                    $this->commands[$fqn] = $definition;
                    break;
                }
            }
        }
    }

    /**
     * @param Query|Command $attribute
     * @param ReflectionClass<object> $class
     * @param ReflectionMethod $method
     * @return array{string, Definition}
     */
    private function toDefinition(Query|Command $attribute, ReflectionClass $class, ReflectionMethod $method): array
    {
        $type = match ($attribute::class) {
            Query::class => 'query',
            Command::class => 'command',
        };

        $throws = $method->getAttributes(Throws::class);

        $parameters = $method->getParameters();
        if (count($parameters) < 1) {
            throw new RuntimeException("Method {$method->name} must have at least one parameter.");
        }

        $attributes = [
            ... $class->getAttributes(Middleware::class),
            ... $method->getAttributes(Middleware::class),
        ];

        $middlewares = empty($attributes) ? [] : array_reduce($attributes, function (array $carry, ReflectionAttribute $attribute) {
            $instance = $attribute->newInstance();
            array_push($carry, ...$instance->middleware);
            return $carry;
        }, []);

        $definition = new Definition(
            $type,
            $class->getName(),
            $method->name,
            $attribute->name ?? $method->name,
            $attribute->namespaceAsString() ?? self::DEFAULT_NAMESPACE,
            $middlewares,
        );

        return [
            $definition->fullyQualifiedName(),
            $definition,
        ];
    }
}