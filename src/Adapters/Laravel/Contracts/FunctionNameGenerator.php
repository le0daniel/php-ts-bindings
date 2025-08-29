<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\OperationDefinition;

interface FunctionNameGenerator
{
    public function generateName(OperationDefinition $definition): string;
}