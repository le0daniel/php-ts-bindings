<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;

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
    public function withMiddlewares(string ...$middlewares): self
    {
        return new self(
            $this->coerceQueryInput,
            [
                ...$this->middleware,
                ...array_values($middlewares),
            ],
        );
    }
}