<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

interface DependsOn
{
    /**
     * @return list<class-string<GeneratesOperationCode|GeneratesLibFiles>>
     */
    public function dependsOnGenerator(): array;
}