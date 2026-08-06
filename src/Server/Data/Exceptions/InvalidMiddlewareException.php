<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;

/**
 * Thrown when a class registered as middleware does not implement MiddlewareContract.
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

    public static function notAMiddleware(string $className): self
    {
        $contract = MiddlewareContract::class;

        return new self("Middleware {$className} must implement {$contract}.");
    }
}
