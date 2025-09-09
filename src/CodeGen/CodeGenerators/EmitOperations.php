<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\Paths;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptCodeBlock;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptImportStatement;

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

    private function generateName(TypedOperation $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->operation->definition->name;
    }

    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): TypescriptCodeBlock
    {
        $definition = $operation->operation->definition;
        $name = $this->generateName($operation);

        return new TypescriptCodeBlock(
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
                new TypescriptImportStatement(
                    from: Paths::libImport("bindings"),
                    imports: ["executeOperation"]
                ),
                new TypescriptImportStatement(
                    from: Paths::libImport("OperationClient"),
                    imports: ["OperationOptions"]
                )
            ]
        );
    }
}