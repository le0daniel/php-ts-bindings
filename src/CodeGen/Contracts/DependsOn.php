<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Contracts;

interface DependsOn
{
    /**
     * @return list<class-string<GeneratesOperationCode|GeneratesLibFiles>>
     */
    public function dependsOnGenerator(): array;

    /**
     * Receives the resolved instance of every generator dependsOnGenerator() declared, keyed by
     * class name, before a single file is generated. Reach for the public API of those instances
     * instead of re-deriving what they emit: the type names EmitOperations declares, for example,
     * depend on the naming rule it was built with and cannot be recomputed from the outside.
     *
     * @param  array<class-string<GeneratesOperationCode|GeneratesLibFiles>, GeneratesOperationCode|GeneratesLibFiles>  $dependencies
     */
    public function setDependencies(array $dependencies): void;
}
