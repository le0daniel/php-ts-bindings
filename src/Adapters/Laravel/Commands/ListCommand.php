<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Give;
use Illuminate\Routing\Router;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Server;

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

        // Both are dereferenced below, so both must exist. This used to be `&&`, which only tripped
        // when neither route was registered and left a null dereference when exactly one was.
        if (!$commandRoute || !$queryRoute) {
            throw new SchemaException('Cannot list routes that are not registered');
        }

        $this->table([
            'PLAIN NAME','URI', 'METHOD', "TARGET", "LARAVEL MIDDLEWARE", "MIDDLEWARE",
        ], array_map(fn(Operation $operation) => match ($operation->definition->type) {
            OperationType::QUERY => [
                $operation->definition->fullyQualifiedName(),
                $this->bindUri($queryRoute->uri(), $operation),
                implode(', ', $queryRoute->methods()),
                $operation->definition->fullyQualifiedClassName . '@' . $operation->definition->methodName,
                implode(', ', $queryRoute->gatherMiddleware()),
                implode(', ', $operation->definition->middleware),
            ],
            OperationType::COMMAND => [
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