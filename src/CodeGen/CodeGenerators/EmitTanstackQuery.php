<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Closure;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Override;

final readonly class EmitTanstackQuery implements GeneratesOperationCode, DependsOn
{
    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
        ];
    }

    /**
     * @param (Closure(TypedOperation):string)|null $nameGenerator
     */
    public function __construct(private ?Closure $nameGenerator = null)
    {
    }

    private function generateName(TypedOperation $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->operation->definition->name;
    }

    #[Override]
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptFile
    {
        $definition = $operation->operation->definition;
        if ($definition->type !== OperationType::QUERY) {
            return null;
        }

        $name = $this->generateName($operation);
        $operationBaseTypeName = ucfirst($name);
        $resultTypeName = $operationBaseTypeName . "Result";
        $resultInputTypeName = $operationBaseTypeName . "Input";
        $queryName = "use" . $operationBaseTypeName . "Query";
        $queryOptionsName = lcfirst($operationBaseTypeName) . "QueryOptions";
        $optionsTypeName = $operationBaseTypeName . "Options";

        $imports = [
            new TypescriptImport(
                "@tanstack/react-query",
                values: ['useQuery', 'queryOptions'],
                types: ['UseQueryOptions'],
            ),
            TypescriptImport::values(Paths::libImport("utils"), 'queryKey'),
            TypescriptImport::values(Paths::libImport("bindings"), 'throwOnFailure'),
        ];

        if ($operation->inputDef->type === 'null') {
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