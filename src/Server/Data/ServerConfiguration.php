<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use NoDiscard;

final readonly class ServerConfiguration
{
    /**
     * @param bool $coerceQueryInput
     * @param list<class-string<MiddlewareContract<mixed>>> $middleware
     */
    public function __construct(
        public bool  $coerceQueryInput = false,
        public array $middleware = [],
    )
    {
    }

    /**
     * @param class-string<MiddlewareContract<mixed>> ...$middlewares
     * @return self
     */
    #[NoDiscard]
    public function withMiddlewares(string ...$middlewares): self
    {
        // array_values is redundant at runtime - spreading two lists yields a list - but it is
        // what tells the type checker so, and $middleware is declared as a list.
        return new self(
            $this->coerceQueryInput,
            [...$this->middleware, ...$middlewares] |> array_values(...),
        );
    }
}