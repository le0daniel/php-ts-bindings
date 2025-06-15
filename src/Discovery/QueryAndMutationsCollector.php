<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Discovery;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Action;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Contracts\Discoverer;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * @phpstan-type Definition array{fqn: string, className: class-string, methodName: string, description?: string}
 */
final class QueryAndMutationsCollector implements Discoverer
{
    private const string DEFAULT_NAMESPACE = 'global';

    /** @var array<string, Definition> */
    private array $queries = [];

    /** @var array<string, Definition> */
    private array $actions = [];

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
                        throw new RuntimeException("Name collision for query: {$fqn} defined in {$definition['className']} -> {$definition['methodName']}.");
                    }
                    $this->queries[$fqn] = $definition;
                    break;
                }

                if ($attribute->getName() === Action::class) {
                    /** @var Action $instance */
                    $instance = $attribute->newInstance();
                    [$fqn, $definition] = $this->toDefinition($instance, $class, $method);
                    if (array_key_exists($fqn, $this->actions)) {
                        throw new RuntimeException("Name collision for query: {$fqn} defined in {$definition['className']} -> {$definition['methodName']}.");
                    }
                    $this->actions[$fqn] = $definition;
                    break;
                }
            }
        }
    }

    /**
     * @param Query|Action $attribute
     * @param ReflectionClass<object> $class
     * @param ReflectionMethod $method
     * @return array{string, Definition}
     */
    private function toDefinition(Query|Action $attribute, ReflectionClass $class, ReflectionMethod $method): array
    {
        $namespace = $attribute->namespaceAsString() ?? self::DEFAULT_NAMESPACE;
        $name = $attribute->name ?? $method->name;
        $fqn = "{$namespace}.{$name}";

        return [
            $fqn,
            [
                'fqn' => $fqn,
                'className' => $class->getName(),
                'methodName' => $method->name,
                'description' => $attribute->description,
            ]
        ];
    }
}