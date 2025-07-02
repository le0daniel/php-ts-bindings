<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;

final readonly class MiddlewarePipeline
{
    /**
     * @param Application $application
     * @param class-string[] $middleware
     */
    public function __construct(
        private Application $application,
        private array       $middleware,
    )
    {
    }

    private function reducer(): Closure
    {
        return function ($stack, string $middlewareClassName) {
            return function (mixed $input, mixed $context, Client $client) use ($stack, $middlewareClassName) {
                $instance = $this->application->make($middlewareClassName);
                return $instance->handle($input, $context, $client, $stack);
            };
        };
    }

    public function execute(mixed $input, mixed $context, Client $client, Closure $then): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware), $this->reducer(), $then,
        );

        return $pipeline($input, $context, $client);
    }
}