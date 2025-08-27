<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ClearOptimizeCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ListCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\OptimizeCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\JustInTimeDiscoveryRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class LaravelServiceProvider extends ServiceProvider implements DeferrableProvider
{
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

        $this->app->singleton(OperationRegistry::class, function (Application $app) {
            if ($this->app->runningInConsole() || !file_exists(base_path('bootstrap/cache/operations.php'))) {
                /** @var Repository $config */
                $config = $app->make('config');
                return JustInTimeDiscoveryRegistry::eagerlyDiscover(
                    $app->make(TypeParser::class),
                    $config->get('operations.discovery_path', []),
                );
            }

            // Gets the cached version
            return require base_path('bootstrap/cache/operations.php');
        });

        $this->app->when(LaravelHttpController::class)
            ->needs(ContextFactory::class)
            ->give(function (Application $app): ?ContextFactory {
                $config = $app->make('config');
                $context = $config->get('operations.context');
                return $context ? $app->make($context) : null;
            });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('operations.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/config/config.php', 'operations'
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