<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Override;

final readonly class EmitOperations implements GeneratesOperationCode, DependsOn
{
    /**
     * @param (Closure(TypedOperation):string)|null $nameGenerator
     */
    public function __construct(
        private ?Closure $nameGenerator = null,
    )
    {
    }

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperationClientBindings::class,
        ];
    }

    /**
     * Depends on the bindings for ordering only, so there is nothing to hold on to.
     */
    #[Override]
    public function setDependencies(array $dependencies): void
    {
    }

    /**
     * The names below are what this generator writes into the operation's module, and the only
     * place they are defined. Whatever else references them — a query key, a hook — asks here
     * rather than re-deriving them: the naming rule lives in this instance, so a second derivation
     * is a second rule waiting to disagree with this one.
     */
    public function operationName(TypedOperation $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->definition->name;
    }

    public function baseTypeName(TypedOperation $operation): string
    {
        return ucfirst($this->operationName($operation));
    }

    public function inputTypeName(TypedOperation $operation): string
    {
        return $this->baseTypeName($operation) . 'Input';
    }

    public function resultTypeName(TypedOperation $operation): string
    {
        return $this->baseTypeName($operation) . 'Result';
    }

    public function errorTypeName(TypedOperation $operation): string
    {
        return $this->baseTypeName($operation) . 'Error';
    }

    /**
     * The types below reference named types by their alias, which lives in the generated types
     * file: every alias the operation's registries carry is imported. Brand is imported
     * unconditionally — inline brands reference it, yet it is never a registry key — and a linter
     * drops it where unused.
     *
     * @return list<TypescriptImport>
     */
    private function aliasImports(TypedOperation $operation): array
    {
        return [
            TypescriptImport::types(Paths::libImport("types"), ['Brand', ...$operation->usedAliases()]),
        ];
    }

    #[Override]
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): TypescriptFile
    {
        $definition = $operation->definition;
        $name = $this->operationName($operation);
        $resultTypeName = $this->resultTypeName($operation);
        $resultInputTypeName = $this->inputTypeName($operation);
        $errorTypeName = $this->errorTypeName($operation);

        $imports = [
            EmitOperationClientBindings::importFromBindings(values: ['executeOperation']),
            TypescriptImport::types(Paths::libImport("OperationClient"), "OperationOptions"),
            ...$this->aliasImports($operation),
        ];
        $docBlock = <<<TypeScript
/**
 * Type: {$definition->type->name}
 * Name: {$definition->fullyQualifiedName()} 
 *
 * @php {$definition->fullyQualifiedClassName}::{$definition->methodName}
 */
TypeScript;

        if (!$operation->hasInput) {
            return new TypescriptFile(
                <<<TypeScript
export type {$resultTypeName} = {$operation->outputDef->type};
export type {$resultInputTypeName} = null;
export type {$errorTypeName} = {$operation->errorDef->type};

{$docBlock}
export async function {$name}(options?: OperationOptions) {
    return await executeOperation<{$resultInputTypeName}, {$resultTypeName}, {$errorTypeName}>(
        '{$definition->type->lowerCase()}', 
        '{$operation->key}', 
        null, 
        options
    )
}
TypeScript, $imports,
            );
        }

        return new TypescriptFile(
            <<<TypeScript
export type {$resultTypeName} = {$operation->outputDef->type};
export type {$resultInputTypeName} = {$operation->inputDef->type};
export type {$errorTypeName} = {$operation->errorDef->type};

{$docBlock}
export async function {$name}(input: {$resultInputTypeName}, options?: OperationOptions) {
    return await executeOperation<{$resultInputTypeName}, {$resultTypeName}, {$errorTypeName}>(
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