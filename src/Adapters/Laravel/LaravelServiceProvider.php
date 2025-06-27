<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ListCommand;
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
        $this->app->singleton(OperationRegistry::class, function ($app) {
            $config = $app->make('config');

            if ($this->app->runningInConsole() || !file_exists(app_path('bootstrap/cache/operations.php'))) {
                return new JustInTimeDiscoveryRegistry($config->get('operations.discovery_path'), new TypeParser());
            }

            return new JustInTimeDiscoveryRegistry($config->get('operations.discovery_path'), new TypeParser());
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
            ]);
        }
    }
}