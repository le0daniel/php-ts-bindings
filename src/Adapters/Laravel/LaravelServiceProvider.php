<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ClearOptimizeCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ListCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\OptimizeCommand;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Operations\CachedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Server\Presenter\CatchAllPresenter;
use Le0daniel\PhpTsBindings\Server\Presenter\ClientAwareExceptionPresenter;
use Le0daniel\PhpTsBindings\Server\Presenter\InvalidInputPresenter;
use Le0daniel\PhpTsBindings\Server\Presenter\NotFoundPresenter;
use Le0daniel\PhpTsBindings\Server\Presenter\UnauthenticatedPresenter;
use Le0daniel\PhpTsBindings\Server\Presenter\UnauthorizedPresenter;
use Le0daniel\PhpTsBindings\Server\Server;

final class LaravelServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Resolves the default configured server via the laravel service provider
     */
    public const string DEFAULT_SERVER = 'operations.default_server';

    /**
     * @return class-string[]
     */
    public function provides(): array
    {
        return [OperationRegistry::class];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TypeParser::class, function () {
            return new TypeParser(
                consumers: TypeParser::defaultConsumers(
                    collectionClasses: [Collection::class]
                ),
            );
        });

        $this->app->singleton(self::DEFAULT_SERVER, function (Application $app): Server {
            $config = $app->make('config');
            $isRepositoryCached = !$this->app->runningInConsole() && file_exists(base_path('bootstrap/cache/operations.php'));

            $repository = $isRepositoryCached
                ? new CachedOperationRegistry(require(base_path('bootstrap/cache/operations.php')))
                : EagerlyLoadedRegistry::eagerlyDiscover(
                    $config->get('operations.discovery_path', []),
                    $app->make(TypeParser::class),
                    match ($config->get('operations.key.mode', 'obfuscate')) {
                        'plain' => EagerlyLoadedRegistry::plainKeyGenerator(),
                        'obfuscate' => EagerlyLoadedRegistry::hashKeyGenerator(
                            $config->get('operations.key.pepper', 'none')
                        ),
                        default => EagerlyLoadedRegistry::hashKeyGenerator('default'),
                    },
                );

            return new Server(
                $repository,
                new SchemaExecutor(),
                [
                    new InvalidInputPresenter(),
                    new UnauthorizedPresenter($config->get('operations.exceptions.unauthorized', [])),
                    new UnauthenticatedPresenter($config->get('operations.exceptions.unauthenticated', [])),
                    new NotFoundPresenter($config->get('operations.exceptions.not_found', [])),
                    new ClientAwareExceptionPresenter(),
                ],
                new CatchAllPresenter(),
            );
        });

        // We bind the default server to the default laravel Http Controller.
        $this->app->bind(LaravelHttpController::class, function (Application $app): LaravelHttpController {
            $config = $app->make('config');
            $context = $config->get('operations.context');

            return new LaravelHttpController(
                $app->make(self::DEFAULT_SERVER),
                $app->make(ExceptionHandler::class),
                $context ? $app->make($context) : null,
                $config->get('app.debug', false),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('operations.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__ . '/config/config.php', 'operations'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListCommand::class,
                OptimizeCommand::class,
                ClearOptimizeCommand::class,
                CodeGenCommand::class,
            ]);
            $this->optimizes(
                'operations:optimize',
                'operations:clear-optimize'
            );
        }
    }
}