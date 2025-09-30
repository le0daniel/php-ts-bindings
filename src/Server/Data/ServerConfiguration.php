<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

final readonly class ServerConfiguration
{
    /**
     * @param bool $coerceQueryInput
     * @param list<class-string> $middleware
     */
    public function __construct(
        public bool  $coerceQueryInput = false,
        public array $middleware = [],
    )
    {
    }

    /**
     * @param class-string ...$middlewares
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