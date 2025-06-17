<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;


interface ExecutionAdapter
{

    /**
     * The input is an array of parameterName => value
     * @param class-string<mixed> $className
     * @param array<string, mixed> $input
     */
    public function executeQuery(string $className, string $methodName, array $input, mixed $request): mixed;

    /**
     * The input is an array of parameterName => value
     * @param class-string<mixed> $className
     * @param array<string, mixed> $input
     */
    public function executeAction(string $className, string $methodName, array $input, mixed $request): mixed;
}