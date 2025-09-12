<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptCodeBlock;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptImportStatement;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;

final class EmitOperations implements GeneratesOperationCode, DependsOn
{

    public function __construct(
        private readonly ?Closure $nameGenerator = null,
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

        $imports = [
            new TypescriptImportStatement(
                from: Paths::libImport("bindings"),
                imports: ["executeOperation"]
            ),
            new TypescriptImportStatement(
                from: Paths::libImport("OperationClient"),
                imports: ["OperationOptions"]
            )
        ];
        $docBlock = <<<TypeScript
/**
 * Type: {$definition->type->name}
 * Name: {$definition->fullyQualifiedName()} 
 *
 * @php {$definition->fullyQualifiedClassName}::{$definition->methodName}
 */
TypeScript;

        if ($operation->inputDefinition === 'null') {
            return new TypescriptCodeBlock(
                <<<TypeScript
{$docBlock}
export async function {$name}(options?: OperationOptions) {
    return await executeOperation<{$operation->inputDefinition}, {$operation->outputDefinition}, {$operation->errorDefinition}>(
        '{$definition->type->lowerCase()}', 
        '{$operation->key}', 
        null, 
        options
    )
}
TypeScript, $imports,
            );
        }

        return new TypescriptCodeBlock(
            <<<TypeScript
{$docBlock}
export async function {$name}(input: {$operation->inputDefinition}, options?: OperationOptions) {
    return await executeOperation<{$operation->inputDefinition}, {$operation->outputDefinition}, {$operation->errorDefinition}>(
        '{$definition->type->lowerCase()}', 
        '{$operation->key}', 
        input, 
        options
    )
}
TypeScript, $imports,
        );
    }
}