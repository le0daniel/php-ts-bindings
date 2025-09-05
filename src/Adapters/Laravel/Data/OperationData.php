<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Data;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;

final class OperationData
{
    public Definition $definition {
        get => $this->operation->definition;
    }

    public string $key {
        get => $this->operation->key;
    }

    public function __construct(
        public readonly string    $inputDefinition,
        public readonly string    $outputDefinition,
        public readonly string    $errorDefinition,
        public readonly Operation $operation,
    )
    {
    }
}