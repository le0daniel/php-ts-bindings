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

final class EmitQueryKey implements DependsOn, GeneratesOperationCode
{
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
        ];
    }

    /**
     * @param (Closure(TypedOperation):string)|null $nameGenerator
     */
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
        if ($definition->type !== OperationType::QUERY) {
            return null;
        }

        $name = $this->generateName($operation);

        // The input definition is inlined verbatim, so the aliases its registry carries must be
        // imported here as well — plus Brand, unconditionally, for inline brands. The file level
        // import merge dedupes them with EmitOperations' imports.
        $aliases = ['Brand', ...$operation->inputDef->registry->usedAliases()];
        sort($aliases);

        $imports = [
            new TypescriptImportStatement(
                from: Paths::libImport("utils"),
                imports: ['queryKey'],
            ),
            new TypescriptImportStatement(
                from: Paths::libImport("types"),
                imports: array_map(fn(string $alias): string => "type {$alias}", $aliases),
            ),
        ];

        return new TypescriptCodeBlock(
            <<<TypeScript
/** @pure */
export function {$name}QueryKey(input: {$operation->inputDef->type}) {
    return queryKey('{$definition->namespace}', '{$definition->name}', input);
}
TypeScript
            ,
            $imports,
        );
    }
}