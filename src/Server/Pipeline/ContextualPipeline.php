<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Pipeline;

use Closure;
use Throwable;

final class ContextualPipeline
{
    private Closure|null $catchErrorsWith = null;
    private Closure|null $then = null;

    /**
     * @param list<object> $pipes
     */
    public function __construct(
        private readonly array $pipes,
    )
    {
    }

    /**
     * @param Closure(Throwable): mixed $closure
     * @return $this
     */
    public function catchErrorsWith(Closure $closure): self
    {
        $this->catchErrorsWith = $closure;
        return $this;
    }

    /**
     * @param Closure(mixed, mixed...): mixed $then
     * @return $this
     */
    public function then(Closure $then): self
    {
        $this->then = $then;
        return $this;
    }

    /**
     * @param list<mixed> $context
     */
    private function reducer(array $context): Closure
    {
        return function ($stack, object $instance) use ($context) {
            return function (mixed $input) use ($stack, $instance, $context) {
                try {
                    return $instance->handle($input, $stack, ...$context);
                } catch (Throwable $exception) {
                    if ($this->catchErrorsWith) {
                        return ($this->catchErrorsWith)($exception);
                    }
                    return $exception;
                }
            };
        };
    }

    public function execute(mixed $value, mixed ... $context): mixed
    {
        $middle = function ($value) use ($context) {
            if ($this->then) {
                try {
                    return ($this->then)($value, ...$context);
                } catch (Throwable $exception) {
                    return $exception;
                }
            }

            return $value;
        };

        $pipeline = array_reduce(
            array_reverse($this->pipes), $this->reducer($context), $middle,
        );

        return $pipeline($value);
    }
}