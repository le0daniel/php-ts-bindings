<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Generators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\FunctionNameGenerator;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\OperationDefinition;

final class DefaultNameGenerator implements FunctionNameGenerator
{

    public function generateName(OperationDefinition $definition): string
    {
        return $definition->name;
    }
}