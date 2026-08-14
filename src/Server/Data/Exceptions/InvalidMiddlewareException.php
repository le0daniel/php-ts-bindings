<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Contracts\ConfigurableMiddleware;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;

/**
 * Thrown when a class registered as middleware does not implement MiddlewareContract, or is
 * given config it cannot accept.
 *
 * Middleware is referenced by class-string - through the #[Middleware] attribute or the global
 * configuration - so the mistake only becomes visible when the operation runs. Saying which class
 * is at fault beats the "call to undefined method handle()" this used to produce.
 */
final class InvalidMiddlewareException extends SchemaException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function notConfigurable(string $className): self
    {
        $contract = ConfigurableMiddleware::class;

        return new self("Middleware {$className} was given config but does not implement {$contract}.");
    }

    public static function invalidConfig(string $className, string $key): self
    {
        return new self("Middleware config for {$className} must be array<string, scalar>, entry '{$key}' is not.");
    }
}
