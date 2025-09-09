<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Closure;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Utils\Paths;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptCodeBlock;
use Le0daniel\PhpTsBindings\CodeGen\Helpers\TypescriptImportStatement;

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

    private function generateName(TypedOperation $operation): string
    {
        return $this->nameGenerator ? ($this->nameGenerator)($operation) : $operation->operation->definition->name;
    }


    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptCodeBlock
    {
        $definition = $operation->operation->definition;
        if ($definition->type !== 'query') {
            return null;
        }

        $name = $this->generateName($operation);

        return new TypescriptCodeBlock(
            <<<TypeScript
/** @pure */
export function {$name}QueryKey(input: {$operation->inputDefinition}) {
    return queryKey('{$definition->namespace}', '{$definition->name}', input);
}
TypeScript
            ,
            [
                new TypescriptImportStatement(
                    from: Paths::libImport("utils"),
                    imports: ['queryKey'],
                ),
            ]
        );
    }
}