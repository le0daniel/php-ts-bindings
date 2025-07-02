<?php

namespace Tests\Unit\Adapters\Laravel\MiddlewarePipeline;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\NullClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline\MiddlewarePipeline;
use RuntimeException;

$containerFactory = fn(Closure $make) => new class ($make) implements Application
{
    public function __construct(private readonly Closure $make)
    {

    }

    public function version()
    {
        throw new RuntimeException();
    }

    public function basePath($path = '')
    {
        throw new RuntimeException();
    }

    public function bootstrapPath($path = '')
    {
        throw new RuntimeException();
    }

    public function configPath($path = '')
    {
        throw new RuntimeException();
    }

    public function databasePath($path = '')
    {
        throw new RuntimeException();
    }

    public function langPath($path = '')
    {
        throw new RuntimeException();
    }

    public function publicPath($path = '')
    {
        throw new RuntimeException();
    }

    public function resourcePath($path = '')
    {
        throw new RuntimeException();
    }

    public function storagePath($path = '')
    {
        throw new RuntimeException();
    }

    public function environment(...$environments)
    {
        throw new RuntimeException();
    }

    public function runningInConsole()
    {
        throw new RuntimeException();
    }

    public function runningUnitTests()
    {
        throw new RuntimeException();
    }

    public function hasDebugModeEnabled()
    {
        throw new RuntimeException();
    }

    public function maintenanceMode()
    {
        throw new RuntimeException();
    }

    public function isDownForMaintenance()
    {
        throw new RuntimeException();
    }

    public function registerConfiguredProviders()
    {
        throw new RuntimeException();
    }

    public function register($provider, $force = false)
    {
        throw new RuntimeException();
    }

    public function registerDeferredProvider($provider, $service = null)
    {
        throw new RuntimeException();
    }

    public function resolveProvider($provider)
    {
        throw new RuntimeException();
    }

    public function boot()
    {
        throw new RuntimeException();
    }

    public function booting($callback)
    {
        throw new RuntimeException();
    }

    public function booted($callback)
    {
        throw new RuntimeException();
    }

    public function bootstrapWith(array $bootstrappers)
    {
        throw new RuntimeException();
    }

    public function getLocale()
    {
        throw new RuntimeException();
    }

    public function getNamespace()
    {
        throw new RuntimeException();
    }

    public function getProviders($provider)
    {
        throw new RuntimeException();
    }

    public function hasBeenBootstrapped()
    {
        throw new RuntimeException();
    }

    public function loadDeferredProviders()
    {
        throw new RuntimeException();
    }

    public function setLocale($locale)
    {
        throw new RuntimeException();
    }

    public function shouldSkipMiddleware()
    {
        throw new RuntimeException();
    }

    public function terminating($callback)
    {
        throw new RuntimeException();
    }

    public function terminate()
    {
        throw new RuntimeException();
    }

    public function get(string $id)
    {
        throw new RuntimeException();
    }

    public function bound($abstract)
    {
        throw new RuntimeException();
    }

    public function alias($abstract, $alias)
    {
        throw new RuntimeException();
    }

    public function tag($abstracts, $tags)
    {
        throw new RuntimeException();
    }

    public function tagged($tag)
    {
        throw new RuntimeException();
    }

    public function bind($abstract, $concrete = null, $shared = false)
    {
        throw new RuntimeException();
    }

    public function bindMethod($method, $callback)
    {
        throw new RuntimeException();
    }

    public function bindIf($abstract, $concrete = null, $shared = false)
    {
        throw new RuntimeException();
    }

    public function singleton($abstract, $concrete = null)
    {
        throw new RuntimeException();
    }

    public function singletonIf($abstract, $concrete = null)
    {
        throw new RuntimeException();
    }

    public function scoped($abstract, $concrete = null)
    {
        throw new RuntimeException();
    }

    public function scopedIf($abstract, $concrete = null)
    {
        throw new RuntimeException();
    }

    public function extend($abstract, Closure $closure)
    {
        throw new RuntimeException();
    }

    public function instance($abstract, $instance)
    {
        throw new RuntimeException();
    }

    public function addContextualBinding($concrete, $abstract, $implementation)
    {
        throw new RuntimeException();
    }

    public function when($concrete)
    {
        throw new RuntimeException();
    }

    public function factory($abstract)
    {
        throw new RuntimeException();
    }

    public function flush()
    {
        throw new RuntimeException();
    }

    public function make($abstract, array $parameters = [])
    {
        return ($this->make)($abstract);
    }

    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        throw new RuntimeException();
    }

    public function resolved($abstract)
    {
        throw new RuntimeException();
    }

    public function beforeResolving($abstract, ?Closure $callback = null)
    {
        throw new RuntimeException();
    }

    public function resolving($abstract, ?Closure $callback = null)
    {
        throw new RuntimeException();
    }

    public function afterResolving($abstract, ?Closure $callback = null)
    {
        throw new RuntimeException();
    }

    public function has(string $id): bool
    {
        throw new RuntimeException();
    }
};

test('execute', function () use ($containerFactory) {

    $container = $containerFactory(fn(string $abstract) => match ($abstract) {
        "first" => new class {
            public function handle(array $input, mixed $context, Client $client, Closure $next): mixed
            {
                $input[] = "first";
                $result = $next($input, $context, $client);
                $result[] = "first";
                return $result;
            }
        },
        "second" => new class {
            public function handle(array $input, mixed $context, Client $client, Closure $next): mixed
            {
                $input[] = "second";
                $result = $next($input, $context, $client);
                $result[] = "second";
                return $result;
            }
        }
    });

    $result = new MiddlewarePipeline($container, ["first", "second"])
        ->execute([], [], new NullClient(), function(array $input) {
            $input[] = "middle";
            return $input;
        });

    expect($result)->toEqual(["first", "second", "middle", "second", "first"]);
});