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

    /**
     * @param array{mixed, ResolveInfo} $context
     * @return Closure
     */
    private function reducer(array $context): Closure
    {
        return function ($stack, string $middlewareClassName) use ($context) {
            return function (mixed $input) use ($stack, $middlewareClassName, $context) {
                $instance = $this->application->make($middlewareClassName);
                return $instance->handle($input, $stack, ...$context);
            };
        };
    }

    /**
     * @param array{mixed} $pipe
     * @param array{mixed, ResolveInfo} $context
     * @param Closure $then
     * @return mixed
     */
    public function execute(mixed $pipe, array $context, Closure $then): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware), $this->reducer($context), $then,
        );

        return $pipeline($pipe);
    }
}