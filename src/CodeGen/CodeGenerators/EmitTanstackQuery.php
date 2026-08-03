<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * Not readonly: the EmitOperations it hangs everything off is injected after construction, which is
 * the only way it can be the same instance the generator runs.
 */
final class EmitTanstackQuery implements GeneratesOperationCode, DependsOn
{
    private EmitOperations $operations;

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->operations = Assertions::instanceOf(
            EmitOperations::class,
            $dependencies[EmitOperations::class] ?? null,
        );
    }

    #[Override]
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptFile
    {
        $definition = $operation->definition;
        if ($definition->type !== OperationType::QUERY) {
            return null;
        }

        // Everything the emitted hook calls or annotates itself with is declared by EmitOperations
        // in the same module, so the names come from there.
        $name = $this->operations->operationName($operation);
        $operationBaseTypeName = $this->operations->baseTypeName($operation);
        $resultTypeName = $this->operations->resultTypeName($operation);
        $resultInputTypeName = $this->operations->inputTypeName($operation);

        $queryName = "use" . $operationBaseTypeName . "Query";
        $queryOptionsName = lcfirst($operationBaseTypeName) . "QueryOptions";
        $optionsTypeName = $operationBaseTypeName . "Options";

        $imports = [
            new TypescriptImport(
                "@tanstack/react-query",
                values: ['useQuery', 'queryOptions'],
                types: ['UseQueryOptions'],
            ),
            EmitTypeUtils::importFromUtils(values: ['queryKey']),
            EmitOperationClientBindings::importFromBindings(values: ['throwOnFailure']),
        ];

        if (!$operation->hasInput) {
            return new TypescriptFile(
                <<<TypeScript
type {$optionsTypeName} = Omit<UseQueryOptions<{$resultTypeName}>, 'queryKey' | 'queryFn'>;

export function {$queryOptionsName}(options?: {$optionsTypeName}) {
    return queryOptions({
        queryKey: queryKey('{$definition->namespace}', '{$definition->name}'),
        queryFn: async ({signal}): Promise<{$resultTypeName}> => {
            const result = await {$name}({signal});
            throwOnFailure(result);
            return result.data;
        },
        ... options,
    });
}

export function {$queryName}(queryOptions?: Partial<{$optionsTypeName}>) {
    return useQuery({$queryOptionsName}(queryOptions));
}
TypeScript, $imports);
        }

        return new TypescriptFile(
            <<<TypeScript
type {$optionsTypeName} = Omit<UseQueryOptions<{$resultTypeName}>, 'queryKey' | 'queryFn'>;

export function {$queryOptionsName}(input: {$resultInputTypeName}, options?: {$optionsTypeName}) {
    return queryOptions({
        queryKey: queryKey('{$definition->namespace}', '{$definition->name}', input),
        queryFn: async ({signal}): Promise<{$resultTypeName}> => {
            const result = await {$name}(input, {signal});
            throwOnFailure(result);
            return result.data;
        },
        ... options,
    });
}

export function {$queryName}(input: {$resultInputTypeName}, queryOptions?: Partial<{$optionsTypeName}>) {
    return useQuery({$queryOptionsName}(input, queryOptions));
}
TypeScript, $imports);
    }
}
