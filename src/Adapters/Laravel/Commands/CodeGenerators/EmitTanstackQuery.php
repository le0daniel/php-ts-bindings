<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ImportStatement;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationCode;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\Paths;

final class EmitTanstackQuery implements GeneratesOperationCode, DependsOn
{
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
        ];
    }

    public function __construct(private readonly ?\Closure $nameGenerator = null)
    {
    }

    private function generateName(OperationData $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->operation->definition->name;
    }

    public function generateOperationCode(OperationData $operation, GeneralMetadata $metadata): ?OperationCode
    {
        $definition = $operation->operation->definition;
        if ($definition->type !== 'query') {
            return null;
        }

        $name = $this->generateName($operation);
        $upperCaseName = ucfirst($name);

        return new OperationCode(
            <<<TypeScript
export function use{$upperCaseName}(input: {$operation->inputDefinition}, queryOptions?: Partial<{enabled: boolean}>) {
    return useQuery({
        queryKey: queryKey('{$definition->namespace}', '{$definition->name}', input),
        queryFn: async ({signal}): Promise<{$operation->outputDefinition}> => {
            const result = await {$name}(input, {signal});
            throwOnFailure(result);
            return result.data;
        },
        ... queryOptions,
    })
}
TypeScript
            ,
            [
                new ImportStatement(
                    from: "@tanstack/react-query",
                    imports: ['useQuery'],
                ),
                new ImportStatement(
                    from: Paths::libImport("utils"),
                    imports: ['queryKey'],
                ),
                new ImportStatement(
                    from: Paths::libImport("bindings"),
                    imports: ['throwOnFailure'],
                )
            ]
        );
    }
}