<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * Not readonly: the generators it imports from are injected after construction, which is the only
 * way they can be the same instances the generator runs.
 */
final class EmitOperations implements DependsOn, GeneratesOperationCode
{
    private EmitTypes $types;

    private EmitOperationClientBindings $bindings;

    /**
     * @param  (Closure(TypedOperation):string)|null  $nameGenerator
     */
    public function __construct(
        private readonly ?Closure $nameGenerator = null,
    ) {
    }

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitTypes::class,
            EmitOperationClientBindings::class,
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->types = Assertions::instanceOf(
            EmitTypes::class,
            $dependencies[EmitTypes::class] ?? null,
        );
        $this->bindings = Assertions::instanceOf(
            EmitOperationClientBindings::class,
            $dependencies[EmitOperationClientBindings::class] ?? null,
        );
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

    /**
     * A query and a command may legitimately share a namespace.name - the registry keys them by
     * type - but both land in the same generated module, and under the default naming rule both
     * emit `export async function get` and `export type GetResult`. That is invalid TypeScript,
     * and it used to be written without a word. The check lives here because the naming rule does.
     *
     * @param  list<TypedOperation>  $operations
     *
     * @throws CodeGenException
     */
    public function assertNamesAreUnique(array $operations): void
    {
        /** @var array<string, TypedOperation> $seen */
        $seen = [];
        foreach ($operations as $operation) {
            $name = $this->operationName($operation);
            $key = "{$operation->definition->namespace}/{$name}";

            if (array_key_exists($key, $seen)) {
                $first = $seen[$key]->definition;
                $second = $operation->definition;
                throw new CodeGenException(
                    "Two operations generate the name '{$name}' in module "
                    ."'{$operation->definition->namespace}.ts': "
                    ."{$first->fullyQualifiedClassName}::{$first->methodName} ({$first->type->lowerCase()}) and "
                    ."{$second->fullyQualifiedClassName}::{$second->methodName} ({$second->type->lowerCase()}). "
                    .'Rename one, or generate with a naming mode that distinguishes them.'
                );
            }

            $seen[$key] = $operation;
        }
    }

    public function baseTypeName(TypedOperation $operation): string
    {
        return ucfirst($this->operationName($operation));
    }

    public function inputTypeName(TypedOperation $operation): string
    {
        return $this->baseTypeName($operation).'Input';
    }

    public function resultTypeName(TypedOperation $operation): string
    {
        return $this->baseTypeName($operation).'Result';
    }

    /**
     * The names the operation exposed, and the whole of what it contributes to its own error type -
     * the rest of the catalogue is the server's, and the types file already declares it as Failure.
     * A consumer that wants the envelope named writes Failure<GetDomainErrors>, so emitting that
     * alias here as well would only be a second name for a type spelled out of one word.
     */
    public function domainErrorTypeName(TypedOperation $operation): string
    {
        return $this->baseTypeName($operation).'DomainErrors';
    }

    /**
     * The types below reference named types by their alias, which lives in the generated types
     * file: every alias the operation's registries carry is imported. Nothing from the error
     * catalogue is among them - a module names none of it. Brand is imported unconditionally —
     * inline brands reference it, yet it is never a registry key — and a linter drops it where
     * unused.
     *
     * @return list<TypescriptImport>
     */
    private function aliasImports(TypedOperation $operation): array
    {
        return [
            $this->types->importFromTypes(types: [
                'Brand',
                ...$operation->usedAliases(),
            ]),
        ];
    }

    #[Override]
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): TypescriptFile
    {
        $definition = $operation->definition;
        $name = $this->operationName($operation);
        $resultTypeName = $this->resultTypeName($operation);
        $resultInputTypeName = $this->inputTypeName($operation);
        $domainErrorTypeName = $this->domainErrorTypeName($operation);

        $imports = [
            $this->bindings->importFromBindings(values: ['executeOperation']),
            $this->bindings->importFromOperationClient(types: ['OperationOptions']),
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

        if (! $operation->hasInput) {
            return new TypescriptFile(
                <<<TypeScript
export type {$resultTypeName} = {$operation->outputDef->type};
export type {$resultInputTypeName} = null;
export type {$domainErrorTypeName} = {$operation->domainErrors};

{$docBlock}
export async function {$name}(options?: OperationOptions) {
    return await executeOperation<{$resultInputTypeName}, {$resultTypeName}, {$domainErrorTypeName}>(
        '{$definition->type->lowerCase()}', 
        '{$operation->key}', 
        null, 
        options
    )
}
TypeScript,
                $imports,
            );
        }

        return new TypescriptFile(
            <<<TypeScript
export type {$resultTypeName} = {$operation->outputDef->type};
export type {$resultInputTypeName} = {$operation->inputDef->type};
export type {$domainErrorTypeName} = {$operation->domainErrors};

{$docBlock}
export async function {$name}(input: {$resultInputTypeName}, options?: OperationOptions) {
    return await executeOperation<{$resultInputTypeName}, {$resultTypeName}, {$domainErrorTypeName}>(
        '{$definition->type->lowerCase()}', 
        '{$operation->key}', 
        input, 
        options
    )
}
TypeScript,
            $imports,
        );
    }
}
