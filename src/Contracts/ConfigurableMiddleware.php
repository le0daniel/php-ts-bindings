<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

/**
 * A middleware that accepts per-operation configuration from its #[Middleware] attribute:
 *
 * ```php
 * #[Middleware(RateLimitMiddleware::class, config: ['limit' => 10])]
 * ```
 *
 * Config is restricted to array<string, scalar> so it can be exported into the operations cache
 * as plain PHP code.
 *
 * The server calls configure() on a private clone of whatever the adapter handed out, so a
 * container-shared instance can never be polluted: mutable classes may assign to $this and return
 * it, readonly classes return `clone($this, [...])`. Either way, return the configured instance -
 * never spread raw config into a clone, pick the keys explicitly.
 *
 * @template-contravariant TContext = mixed
 *
 * @extends MiddlewareContract<TContext>
 */
interface ConfigurableMiddleware extends MiddlewareContract
{
    /**
     * @param  array<string, scalar>  $config
     */
    public function configure(array $config): static;
}
