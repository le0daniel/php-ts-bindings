<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;

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
                implode(', ', $operation->definition->middleware),
                $typescriptDefinition->toDefinition($operation->inputNode(), DefinitionTarget::INPUT),
                $typescriptDefinition->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT),
            ],
            'command' => [
                $this->bindUri($commandRoute->uri(), $operation),
                implode(', ', $commandRoute->methods()),
                $operation->definition->fullyQualifiedClassName . '@' . $operation->definition->methodName,
                implode(', ', $commandRoute->gatherMiddleware()),
                implode(', ', $operation->definition->middleware),
                $typescriptDefinition->toDefinition($operation->inputNode(), DefinitionTarget::INPUT),
                $typescriptDefinition->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT),
            ],
        }, $registry->all()));

        return 0;
    }

    private function bindUri(string $uri, Operation $operation): string
    {
        return str_replace('{fqn}', $operation->definition->fullyQualifiedName(), $uri);
    }
}