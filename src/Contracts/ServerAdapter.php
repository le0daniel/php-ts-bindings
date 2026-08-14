<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface ServerAdapter
{
    /**
     * @param  class-string<MiddlewareContract>  $className
     */
    public function createMiddleware(string $className): MiddlewareContract;

    /**
     * @template TClass
     *
     * @param  class-string<TClass>  $className
     * @return TClass
     */
    public function createController(string $className): mixed;
}
