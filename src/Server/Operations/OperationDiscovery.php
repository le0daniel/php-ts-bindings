<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Operations;

use Closure;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

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
            if (count($attributes) === 0) {
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
     * A handler is called positionally with exactly ($input, $context, $client) and may declare a
     * *prefix* of those - nothing inspects the signature at call time. Declaring
     * `(array $input, Client $client)` therefore receives the context in the client slot and dies
     * with a TypeError that says nothing about the real mistake, so the shape is checked once,
     * here, where the method is already being reflected.
     *
     * The first parameter also defines the entire published input contract, so getting it wrong
     * publishes a type the client can never satisfy rather than failing.
     *
     * @param ReflectionClass<object> $class
     */
    private static function assertHandlerSignature(ReflectionClass $class, ReflectionMethod $method): void
    {
        $signature = "{$class->getName()}::{$method->name}";
        $parameters = $method->getParameters();

        if (count($parameters) < 1) {
            throw new SchemaException(
                "Operation {$signature} must declare at least one parameter: the first one is the "
                . "input, and its type is the contract the client must satisfy."
            );
        }

        if (count($parameters) > 3) {
            throw new SchemaException(
                "Operation {$signature} declares " . count($parameters) . " parameters. A handler is "
                . "called with (\$input, \$context, \$client) and may declare a prefix of those."
            );
        }

        // The client is the third argument. A Client in second position is the common slip and is
        // worth naming, because the value it would actually receive is the context.
        $secondParameterType = ($parameters[1] ?? null)?->getType();
        if ($secondParameterType instanceof ReflectionNamedType && is_a($secondParameterType->getName(), Client::class, true)) {
            throw new SchemaException(
                "Operation {$signature} declares {$secondParameterType->getName()} as its second "
                . "parameter, but the second argument is the context. Declare the client third: "
                . "(\$input, \$context, Client \$client)."
            );
        }

        $thirdParameterType = ($parameters[2] ?? null)?->getType();
        if ($thirdParameterType instanceof ReflectionNamedType) {
            $declared = $thirdParameterType->getName();

            // is_a() this way round asks whether a Client satisfies what was declared, so Client
            // itself and any interface it implements are accepted.
            if ($declared !== 'mixed' && !is_a(Client::class, $declared, true)) {
                throw new SchemaException(
                    "Operation {$signature} declares {$declared} as its third parameter, which is "
                    . "the client. It has to accept " . Client::class . "."
                );
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

        self::assertHandlerSignature($class, $method);

        // Collect all middlewares, on the class and the method itself. Order is load bearing: it
        // is the order ContextualPipeline nests them in, so class-level middleware wraps
        // method-level middleware.
        $middlewareAttributes = [
            ... $class->getAttributes(Middleware::class),
            ... $method->getAttributes(Middleware::class),
        ];

        /** @var list<class-string<MiddlewareContract<mixed>>> $middlewares */
        $middlewares = [];
        foreach ($middlewareAttributes as $middlewareAttribute) {
            $middlewares[] = $middlewareAttribute->newInstance()->middleware;
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