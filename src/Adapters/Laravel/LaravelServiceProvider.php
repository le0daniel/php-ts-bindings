<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ClearOptimizeCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\ListCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\OptimizeCommand;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ClientFactory;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Adapters\PsrContainerAdapter;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\HashSha256KeyGenerator;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Preloader;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

final class LaravelServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Resolves the default-configured server via the laravel service provider
     */
    public const string DEFAULT_SERVER = 'operations.default_server';

    /**
     * @return class-string[]
     */
    #[Override]
    public function provides(): array
    {
        // @phpstan-ignore-next-line return.type -- allowed here.
        return [
            TypeParser::class,
            LaravelHttpController::class,
            Preloader::class,
            self::DEFAULT_SERVER,
        ];
    }

    /**
     * The one place operations.key is read. Preloader has to derive keys exactly as the registry
     * does or a preloaded query is simply not found, and two copies of this match were how they
     * would come to disagree.
     *
     * Every failure is loud: an unrecognised mode used to fall through to a different pepper than
     * the configured one, so a typo in the config silently changed every key in the application.
     */
    private static function keyGeneratorFrom(Application $app): OperationKeyGenerator
    {
        $config = $app->make('config');
        $mode = $config->get('operations.key.mode', 'obfuscate');

        return match ($mode) {
            'plain' => new PlainlyExposedKeyGenerator(),
            'obfuscate' => new HashSha256KeyGenerator($config->get('operations.key.pepper', 'none')),
            'custom' => self::customKeyGenerator($app, $config->get('operations.key.className')),
            default => throw new InvalidArgumentException(
                "Invalid operations.key.mode '{$mode}'. Use 'obfuscate', 'plain' or 'custom'."
            ),
        };
    }

    private static function customKeyGenerator(Application $app, mixed $className): OperationKeyGenerator
    {
        if (!is_string($className) || $className === '') {
            throw new InvalidArgumentException(
                "operations.key.mode is 'custom', so operations.key.className must name a class "
                . 'implementing ' . OperationKeyGenerator::class . '.'
            );
        }

        return Assertions::instanceOf(OperationKeyGenerator::class, $app->make($className));
    }

    public static function serverFactory(
        Application        $app,
        ?OperationRegistry $operations,
    ): Server
    {
        $config = $app->make('config');

        $operations ??= EagerlyLoadedOperationRegistry::eagerlyDiscover(
            $config->get('operations.discovery_path', []),
            $app->make(TypeParser::class),
            self::keyGeneratorFrom($app),
        );

        /** @var list<class-string<MiddlewareContract>> $middlewares */
        $middlewares = $config->get('operations.middleware', []) |> array_values(...);

        return new Server(
            registry: $operations,
            adapter: new PsrContainerAdapter(container: $app),
            configuration: new ServerConfiguration()
                ->withMiddlewares(...$middlewares)
                ->withExceptions(
                    notFound: $config->get('operations.exceptions.not_found', []),
                    unauthenticated: $config->get('operations.exceptions.unauthenticated', []),
                    unauthorized: $config->get('operations.exceptions.unauthorized', []),
                ),
        );
    }

    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(TypeParser::class, function () {
            return new TypeParser(
                consumers: TypeParser::defaultConsumers(),
            );
        });

        $this->app->singleton(self::DEFAULT_SERVER, function (Application $app): Server {
            $isRepositoryCached = file_exists(base_path('bootstrap/cache/operations.php'));

            return self::serverFactory(
                $app,
                $isRepositoryCached ? require(base_path('bootstrap/cache/operations.php')) : null
            );
        });

        $this->app->singleton(Preloader::class, function (Application $app): Preloader {
            return new Preloader(
                server: $app->make(self::DEFAULT_SERVER),
                keyGenerator: self::keyGeneratorFrom($app),
            );
        });

        // We bind the default server to the default laravel Http Controller.
        $this->app->bind(LaravelHttpController::class, function (Application $app): LaravelHttpController {
            $config = $app->make('config');

            /** @var class-string<ContextFactory> $contextFactoryClassName */
            $contextFactoryClassName = $config->get('operations.context');

            /** @var class-string<ClientFactory>|null $clientFactoryClassName */
            $clientFactoryClassName = $config->get('operations.client');

            return new LaravelHttpController(
                $app->make(self::DEFAULT_SERVER),
                $app->make(ExceptionHandler::class),
                $contextFactoryClassName ? $app->make($contextFactoryClassName) : null,
                $clientFactoryClassName === null
                    ? new OperationClientFactory()
                    : $app->make($clientFactoryClassName),
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
            __DIR__ . '/config/config.php',
            'operations'
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
