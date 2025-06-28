<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ListCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\OptimizeCommand;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\JustInTimeDiscoveryRegistry;
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
            return new TypeParser();
        });

        $this->app->singleton(OperationRegistry::class, function (Application $app) {
            if ($this->app->runningInConsole() || !file_exists(app_path('bootstrap/cache/operations.php'))) {
                /** @var Repository $config */
                $config = $app->make('config');
                return new JustInTimeDiscoveryRegistry($config->get('operations.discovery_path'), new TypeParser());
            }

            // Gets the cached version
            return require app_path('bootstrap/cache/operations.php');
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
                CodeGenCommand::class,
            ]);
        }
    }
}