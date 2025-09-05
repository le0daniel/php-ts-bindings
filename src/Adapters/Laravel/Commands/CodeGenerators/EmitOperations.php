<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ImportStatement;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationCode;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\Paths;

final class EmitOperations implements GeneratesOperationCode, DependsOn
{

    public function __construct(
        private readonly ?\Closure $nameGenerator = null,
    )
    {
    }

    public function dependsOnGenerator(): array
    {
        return [
            EmitOperationClientBindings::class,
        ];
    }

    private function generateName(OperationData $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->operation->definition->name;
    }

    public function generateOperationCode(OperationData $operation, GeneralMetadata $metadata): OperationCode
    {
        $definition = $operation->operation->definition;
        $name = $this->generateName($operation);

        return new OperationCode(
            <<<TypeScript
/**
 * Type: {$definition->type}
 * Name: {$definition->fullyQualifiedName()} 
 *
 * @php {$definition->fullyQualifiedClassName}::{$definition->methodName}
 */
export async function {$name}(input: $operation->inputDefinition, options?: OperationOptions) {
    return await executeOperation<{$operation->inputDefinition}, {$operation->outputDefinition}, {$operation->errorDefinition}>(
        '{$definition->type}', 
        '{$operation->key}', 
        input, 
        options
    )
}
TypeScript
            ,
            [
                new ImportStatement(
                    from: Paths::libImport("bindings"),
                    imports: ["executeOperation"]
                ),
                new ImportStatement(
                    from: Paths::libImport("OperationClient"),
                    imports: ["OperationOptions"]
                )
            ]
        );
    }
}