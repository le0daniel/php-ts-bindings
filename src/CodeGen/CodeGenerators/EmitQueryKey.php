<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesOperationCode;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * Not readonly: the generators it takes its names and its imports from are injected after
 * construction, which is the only way they can be the same instances the generator runs.
 */
final class EmitQueryKey implements DependsOn, GeneratesOperationCode
{
    private EmitOperations $operations;
    private EmitTypeUtils $utils;

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitOperations::class,
            EmitTypeUtils::class,
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->operations = Assertions::instanceOf(
            EmitOperations::class,
            $dependencies[EmitOperations::class] ?? null,
        );
        $this->utils = Assertions::instanceOf(
            EmitTypeUtils::class,
            $dependencies[EmitTypeUtils::class] ?? null,
        );
    }

    #[Override]
    public function generateOperationCode(TypedOperation $operation, ServerMetadata $metadata): ?TypescriptFile
    {
        $definition = $operation->definition;
        if ($definition->type !== OperationType::QUERY) {
            return null;
        }

        // The input type EmitOperations exports is referenced rather than inlined, so this needs no
        // import of its own: the alias it may be built from is already imported by the module that
        // declares it.
        $name = $this->operations->operationName($operation);
        $inputTypeName = $this->operations->inputTypeName($operation);

        return new TypescriptFile(
            <<<TypeScript
/** @pure */
export function {$name}QueryKey(input: {$inputTypeName}) {
    return queryKey('{$definition->namespace}', '{$definition->name}', input);
}
TypeScript
            ,
            imports: [
                $this->utils->importFromUtils(values: ['queryKey']),
            ],
        );
    }
}
