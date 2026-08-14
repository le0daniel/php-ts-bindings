<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;

/**
 * Runs a middleware around this operation. Repeat the attribute to attach several, the way
 * #[Throws] is repeated: they apply outermost first, and every middleware declared on the class
 * runs outside every middleware declared on the method.
 *
 * Config requires the middleware to implement ConfigurableMiddleware and is limited to
 * array<string, scalar> - it is exported into the operations cache as plain PHP code.
 *
 * ```php
 * #[Command('users')]
 * #[Middleware(AuthMiddleware::class)]
 * #[Middleware(RateLimitMiddleware::class, config: ['limit' => 10])]
 * public function create(array $input): array { }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Middleware
{
    /**
     * @param  class-string<MiddlewareContract<mixed>>  $middleware
     * @param  array<string, scalar>  $config
     */
    public function __construct(
        public string $middleware,
        public array $config = [],
    ) {
    }
}
