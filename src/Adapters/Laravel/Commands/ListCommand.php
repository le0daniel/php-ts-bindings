<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Give;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Server;
use RuntimeException;

final class ListCommand extends Command
{
    protected $signature = 'operations:list';
    protected $description = 'Send a marketing email to a user';

    public function handle(
        #[Give(LaravelServiceProvider::DEFAULT_SERVER)] Server $server,
        Router                                                 $router
    ): int
    {
        $queryRoute = $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME);
        $commandRoute = $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME);

        if (!$commandRoute && !$queryRoute) {
            throw new RuntimeException('Cannot list routes that are not registered');
        }

        $this->table([
            'PLAIN NAME','URI', 'METHOD', "TARGET", "LARAVEL MIDDLEWARE", "MIDDLEWARE",
        ], array_map(fn(Operation $operation) => match ($operation->definition->type) {
            'query' => [
                $operation->definition->fullyQualifiedName(),
                $this->bindUri($queryRoute->uri(), $operation),
                implode(', ', $queryRoute->methods()),
                $operation->definition->fullyQualifiedClassName . '@' . $operation->definition->methodName,
                implode(', ', $queryRoute->gatherMiddleware()),
                implode(', ', $operation->definition->middleware),
            ],
            'command' => [
                $operation->definition->fullyQualifiedName(),
                $this->bindUri($commandRoute->uri(), $operation),
                implode(', ', $commandRoute->methods()),
                $operation->definition->fullyQualifiedClassName . '@' . $operation->definition->methodName,
                implode(', ', $commandRoute->gatherMiddleware()),
                implode(', ', $operation->definition->middleware),
            ],
        }, $server->registry->all()));

        return 0;
    }

    private function bindUri(string $uri, Operation $operation): string
    {
        return str_replace('{fqn}', $operation->key, $uri);
    }
}