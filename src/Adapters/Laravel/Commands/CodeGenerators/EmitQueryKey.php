<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Closure;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ImportStatement;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationCode;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\Paths;

final class EmitQueryKey implements DependsOn, GeneratesOperationCode
{
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
        ];
    }

    public function __construct(private readonly ?Closure $nameGenerator = null)
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

        return new OperationCode(
            <<<TypeScript
/** @pure */
export function {$name}QueryKey(input: {$operation->inputDefinition}) {
    return queryKey('{$definition->namespace}', '{$definition->name}', input);
}
TypeScript
            ,
            [
                new ImportStatement(
                    from: Paths::libImport("utils"),
                    imports: ['queryKey'],
                ),
            ]
        );
    }
}