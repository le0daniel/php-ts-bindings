<?php

namespace Tests\Unit\Adapters\Laravel\MiddlewarePipeline;

use Closure;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\NullClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline\MiddlewarePipeline;
use Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline\ResolveInfo;
use Mockery;

test('execute', function () {

    $container = Mockery::mock(Application::class);
    $container->shouldReceive("make")->with("first")->andReturn(new class {
        public function handle(array $input, Closure $next): mixed
        {
            $input[] = "first";
            $result = $next($input);
            $result[] = "first";
            return $result;
        }
    });
    $container->shouldReceive("make")->with("second")->andReturn(new class {
        public function handle(array $input, Closure $next, null $context, ResolveInfo $info): mixed
        {
            $input[] = "second:{$info->namespace}";
            $result = $next($input);
            $result[] = "second";
            return $result;
        }
    });

    $resolveInfo = new ResolveInfo('ns', 'myName', 'command', 'cn', 'mn');

    $result = new MiddlewarePipeline($container, ["first", "second"])
        ->execute([], [null, $resolveInfo], function(array $input) {
            $input[] = "middle";
            return $input;
        });

    expect($result)->toEqual(["first", "second:ns", "middle", "second", "first"]);
});

test("exception caught in fn", function () {
    $container = Mockery::mock(Application::class);
    $container->shouldReceive("make")->with("first")->andReturn(new class {
        public function handle(array $input, Closure $next): mixed
        {
            throw new Exception("test: " . $next($input));
        }
    });

    $resolveInfo = new ResolveInfo('ns', 'myName', 'command', 'cn', 'mn');

    $result = new MiddlewarePipeline($container, ["first"])
        ->catch(fn(\Throwable $throwable) => $throwable)
        ->execute([], [null, $resolveInfo], function(array $input) {
            return "middle";
        });

    expect($result)->toBeInstanceOf(Exception::class);
    expect($result->getMessage())->toBe("test: middle");
});

test("Throws without an exception fn", function () {
    $container = Mockery::mock(Application::class);
    $container->shouldReceive("make")->with("first")->andReturn(new class {
        public function handle(array $input, Closure $next): mixed
        {
            throw new Exception("test: " . $next($input));
        }
    });

    $resolveInfo = new ResolveInfo('ns', 'myName', 'command', 'cn', 'mn');

    $pipeline = new MiddlewarePipeline($container, ["first"]);
    expect(fn() => $pipeline->execute([], [null, $resolveInfo], function(array $input) {
        return "middle";
    }))->toThrow(Exception::class);
});