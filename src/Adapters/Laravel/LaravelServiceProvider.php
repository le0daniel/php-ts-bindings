<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Le0daniel\PhpTsBindings\BindingsManager;
use Le0daniel\PhpTsBindings\Executor\Registry\JustInTimeDiscoveryRegistry;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class LaravelServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function provides(): array
    {
        return [BindingsManager::class];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BindingsManager::class, function () {
            return new BindingsManager(
                new LaravelHttpRequestAdapter(function () {
                    return null;
                }),
                new JustInTimeDiscoveryRegistry(app_path('Operations'), new TypeParser()),
                new SchemaExecutor(),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}