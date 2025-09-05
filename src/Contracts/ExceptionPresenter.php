<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Throwable;

interface ExceptionPresenter
{
    /**
     * @param Throwable $throwable
     * @param Definition $definition
     * @return bool
     */
    public function matches(Throwable $throwable, Definition $definition): bool;

    /**
     * Return a TypeScript definition for the errors. This is used to define the operationSchema.
     * To create an operation-specific handler, render null if there is no exception to match.
     *
     * @param Definition $definition
     * @return string|null
     */
    public function toTypeScriptDefinition(Definition $definition): ?string;

    /**
     * Render a response compatible with the current definition
     * @param Throwable $throwable
     * @return array<string, mixed>
     */
    public function details(Throwable $throwable): array;

    /**
     * Transport layer status code.
     * @return ErrorType
     */
    public static function errorType(): ErrorType;
}