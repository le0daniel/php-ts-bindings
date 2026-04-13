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
use Le0daniel\PhpTsBindings\Server\Data\OperationType;

final readonly class EmitTanstackQuery implements GeneratesOperationCode, DependsOn
{
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
        ];
    }

    public function __construct(private ?Closure $nameGenerator = null)
    {
    }

    private function generateName(TypedOperation $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->operation->definition->name;
    }

    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptCodeBlock
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
            new TypescriptImportStatement(
                from: "@tanstack/react-query",
                imports: ['useQuery', 'UseQueryOptions', 'queryOptions'],
            ),
            new TypescriptImportStatement(
                from: Paths::libImport("utils"),
                imports: ['queryKey'],
            ),
            new TypescriptImportStatement(
                from: Paths::libImport("bindings"),
                imports: ['throwOnFailure'],
            ),
        ];



        if ($operation->inputDefinition === 'null') {
            return new TypescriptCodeBlock(
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

        return new TypescriptCodeBlock(
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