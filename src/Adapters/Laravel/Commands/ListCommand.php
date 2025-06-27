<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;
use ReflectionAttribute;

final class ListCommand extends Command
{
    protected $signature = 'operations:list';
    protected $description = 'Send a marketing email to a user';
    public function handle(OperationRegistry $registry, Router $router): int {
        $queryRoute = $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME);
        $commandRoute = $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME);

        if (!$commandRoute || !$queryRoute) {
            throw new \RuntimeException('Cannot list routes that are not registered');
        }

        foreach ($registry->all() as $operation) {
            $uri = match ($operation->definition->type) {
                'query' => $this->bindUri($queryRoute->uri(), $operation),
                'command' => $this->bindUri($commandRoute->uri(), $operation),
            };
        }

        $typescriptDefinition = new TypescriptDefinitionGenerator();

        $this->table([
            'URI', 'METHOD', "TARGET", "LARAVEL MIDDLEWARE", "MIDDLEWARE", "INPUT", "OUTPUT",
        ], array_map(fn(Operation $operation) => match ($operation->definition->type) {
            'query' => [
                $this->bindUri($queryRoute->uri(), $operation),
                implode(', ', $queryRoute->methods()),
                $operation->definition->fullyQualifiedClassName . '@' . $operation->definition->methodName,
                implode(', ', $queryRoute->gatherMiddleware()),
                implode(', ', $this->collectMiddleware($operation->definition)),
                $typescriptDefinition->toDefinition($operation->inputNode(), DefinitionTarget::INPUT),
                $typescriptDefinition->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT),
            ],
            'command' => [
                $this->bindUri($commandRoute->uri(), $operation),
                implode(', ', $commandRoute->methods()),
                $operation->definition->fullyQualifiedClassName . '@' . $operation->definition->methodName,
                implode(', ', $commandRoute->gatherMiddleware()),
                implode(', ', $this->collectMiddleware($operation->definition)),
                $typescriptDefinition->toDefinition($operation->inputNode(), DefinitionTarget::INPUT),
                $typescriptDefinition->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT),
            ],
        }, $registry->all()));

        return 0;
    }

    /**
     * @param OperationDefinition $definition
     * @return array<class-string>
     * @throws \ReflectionException
     */
    private function collectMiddleware(OperationDefinition $definition): array
    {
        $reflectionClass = new \ReflectionClass($definition->fullyQualifiedClassName);
        $attributes = [
            ... $reflectionClass->getAttributes(Middleware::class),
            ... $reflectionClass->getMethod($definition->methodName)->getAttributes(Middleware::class),
        ];

        return array_reduce($attributes, function (array $carry, ReflectionAttribute $attribute) {
            $instance = $attribute->newInstance();
            array_push($carry, ...$instance->middleware);
            return $carry;
        }, []);
    }

    private function bindUri(string $uri, Operation $operation): string
    {
        return str_replace('{fqn}', $operation->definition->fullyQualifiedName(), $uri);
    }
}