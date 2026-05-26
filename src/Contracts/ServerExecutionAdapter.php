<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface ServerExecutionAdapter
{
    /**
     * @param class-string $className
     * @return object
     */
    public function createMiddleware(string $className): object;

    /**
     * @param class-string $className
     * @return object
     */
    public function createOperationClass(string $className): object;
}