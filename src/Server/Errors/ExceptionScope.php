<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Errors;

use ReflectionException;
use ReflectionMethod;

/**
 * The scope an exception was thrown from - always a full class name and method name: the operation
 * handler, or a middleware's handle(). Only this scope's #[Throws] declarations may say what the
 * exception means - a declaration never covers a throw from another scope.
 */
final readonly class ExceptionScope
{
    /**
     * @param  class-string  $className
     */
    public function __construct(
        public string $className,
        public string $methodName,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function toReflection(): ReflectionMethod
    {
        return new ReflectionMethod($this->className, $this->methodName);
    }
}
