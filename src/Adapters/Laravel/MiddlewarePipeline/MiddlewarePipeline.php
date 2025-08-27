<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * @phpstan-type CatchFn Closure(Throwable): mixed
 */
final class MiddlewarePipeline
{
    /**
     * @var CatchFn|null
     */
    private Closure|null $catch = null;

    /**
     * @param Application $application
     * @param class-string[] $middleware
     */
    public function __construct(
        private readonly Application $application,
        private readonly array       $middleware,
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
     * @param CatchFn $catch
     * @return $this
     */
    public function catch(Closure $catch): self
    {
        $this->catch = $catch;
        return $this;
    }

    /**
     * @param array{mixed} $pipe
     * @param array{mixed, ResolveInfo} $context
     * @param Closure $then
     * @return mixed
     * @throws Throwable
     */
    public function execute(mixed $pipe, array $context, Closure $then): mixed
    {
        try {
            $pipeline = array_reduce(
                array_reverse($this->middleware), $this->reducer($context), $then,
            );

            return $pipeline($pipe);
        } catch (Throwable $e) {
            if ($this->catch) {
                return ($this->catch)($e);
            }
            throw $e;
        }
    }
}