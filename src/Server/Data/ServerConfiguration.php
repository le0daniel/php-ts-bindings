<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use NoDiscard;
use Throwable;

final readonly class ServerConfiguration
{
    /**
     * The exception lists map application exceptions onto the server's finite error catalogue.
     * Matching is instanceof, so listing a base class covers every subclass of it.
     *
     * @param  list<class-string<MiddlewareContract<mixed>>>  $middleware
     * @param  list<class-string<Throwable>>  $notFoundExceptions
     * @param  list<class-string<Throwable>>  $unauthenticatedExceptions
     * @param  list<class-string<Throwable>>  $unauthorizedExceptions
     */
    public function __construct(
        public bool $coerceQueryInput = false,
        public array $middleware = [],
        public array $notFoundExceptions = [],
        public array $unauthenticatedExceptions = [],
        public array $unauthorizedExceptions = [],
    ) {
    }

    /**
     * @param  class-string<MiddlewareContract<mixed>>  ...$middlewares
     */
    #[NoDiscard]
    public function withMiddlewares(string ...$middlewares): self
    {
        // array_values is redundant at runtime - spreading two lists yields a list - but it is
        // what tells the type checker so, and $middleware is declared as a list.
        return new self(
            coerceQueryInput: $this->coerceQueryInput,
            middleware: [...$this->middleware, ...$middlewares] |> array_values(...),
            notFoundExceptions: $this->notFoundExceptions,
            unauthenticatedExceptions: $this->unauthenticatedExceptions,
            unauthorizedExceptions: $this->unauthorizedExceptions,
        );
    }

    /**
     * Appends to the existing lists. An omitted category is left untouched.
     *
     * @param  list<class-string<Throwable>>  $notFound
     * @param  list<class-string<Throwable>>  $unauthenticated
     * @param  list<class-string<Throwable>>  $unauthorized
     */
    #[NoDiscard]
    public function withExceptions(
        array $notFound = [],
        array $unauthenticated = [],
        array $unauthorized = [],
    ): self {
        return new self(
            coerceQueryInput: $this->coerceQueryInput,
            middleware: $this->middleware,
            notFoundExceptions: [...$this->notFoundExceptions, ...$notFound],
            unauthenticatedExceptions: [...$this->unauthenticatedExceptions, ...$unauthenticated],
            unauthorizedExceptions: [...$this->unauthorizedExceptions, ...$unauthorized],
        );
    }
}
