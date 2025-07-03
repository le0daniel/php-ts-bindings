<?php

namespace Tests\Unit\Adapters\Laravel\MiddlewarePipeline;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\NullClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline\MiddlewarePipeline;
use Mockery;

test('execute', function () {

    $container = Mockery::mock(Application::class);
    $container->shouldReceive("make")->with("first")->andReturn(new class {
        public function handle(array $input, mixed $context, Client $client, Closure $next): mixed
        {
            $input[] = "first";
            $result = $next($input, $context, $client);
            $result[] = "first";
            return $result;
        }
    });
    $container->shouldReceive("make")->with("second")->andReturn(new class {
        public function handle(array $input, mixed $context, Client $client, Closure $next): mixed
        {
            $input[] = "second";
            $result = $next($input, $context, $client);
            $result[] = "second";
            return $result;
        }
    });

    $result = new MiddlewarePipeline($container, ["first", "second"])
        ->execute([], [], new NullClient(), function(array $input) {
            $input[] = "middle";
            return $input;
        });

    expect($result)->toEqual(["first", "second", "middle", "second", "first"]);
});