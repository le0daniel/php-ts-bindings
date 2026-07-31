<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Data;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;

final class TypedOperation
{
    public Definition $definition {
        get => $this->operation->definition;
    }

    public string $key {
        get => $this->operation->key;
    }

    /**
     * Each definition carries its own registry with every alias it relies on: what the operation's
     * file imports, and what the generated types file declares (via the run's shared registry).
     */
    public function __construct(
        public readonly Typescript $inputDef,
        public readonly Typescript $outputDef,
        public readonly Typescript $errorDef,
        public readonly Operation  $operation,
    )
    {
    }

    /**
     * The aliases the operation's own file references, ready to import.
     *
     * @return list<string> sorted
     */
    public function usedAliases(): array
    {
        $aliases = array_values(array_unique([
            ...$this->inputDef->registry->usedAliases(),
            ...$this->outputDef->registry->usedAliases(),
            ...$this->errorDef->registry->usedAliases(),
        ]));
        sort($aliases);
        return $aliases;
    }
}
