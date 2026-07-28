<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Data;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;

final class TypedOperation
{
    public Definition $definition {
        get => $this->operation->definition;
    }

    public string $key {
        get => $this->operation->key;
    }

    /**
     * @param string $inputDefinition The input type. Branded leaves appear as their alias name.
     * @param string $outputDefinition The output type. Branded leaves appear as their alias name.
     * @param TypeRegistry $registry The aliases the two types above refer to. A generator writing
     *        into the file that declares them emits one `export type` per entry; a generator writing
     *        into any other file imports them by name.
     */
    public function __construct(
        public readonly string       $inputDefinition,
        public readonly string       $outputDefinition,
        public readonly string       $errorDefinition,
        public readonly Operation    $operation,
        public readonly TypeRegistry $registry = new TypeRegistry(),
    )
    {
    }
}