<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * Not readonly: the EmitTypes whose file it writes into is injected after construction, which is the
 * only way it can be the same instance the generator runs.
 */
final class EmitTypeMap implements GeneratesLibFiles, DependsOn
{
    private EmitTypes $emitTypes;

    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        /**
         * @var array<"query"|"command", array<string, array{input: string, output: string, errors: string}>> $map
         */
        $map = array_reduce($operations, function (array $carry, TypedOperation $operation): array {
            $carry[$operation->definition->type->lowerCase()][$operation->definition->fullyQualifiedName()] = [
                'input' => $operation->inputDef->type,
                'output' => $operation->outputDef->type,
                'errors' => $operation->errorDef->type,
            ];
            return $carry;
        }, []);

        $mapAsTsTypeString = '{' . implode(';', Arrays::mapWithKeys($map, function (string $type, array $operations) {
                $typeString = implode(';', Arrays::mapWithKeys($operations, function (string $operation, array $definition) {
                    return "'{$operation}': {input: {$definition['input']}, output: {$definition['output']}, errors: {$definition['errors']}}";
                }));
                return "{$type}: {{$typeString}}";
            })) . '}';

        // Written into the types file rather than one of its own: the map inlines the aliases
        // EmitTypes declares, and they only resolve while it sits next to them.
        return [
            'type-map' => new TypescriptFile(<<<TypeScript
/**
 * Full type map of all operations, input and output types.
 */
export type TypeMap = {$mapAsTsTypeString};
TypeScript,
                imports: [
                    $this->emitTypes->importFromTypes(types: $registry->usedAliases())
                ]
            )
        ];
    }

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [EmitTypes::class];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->emitTypes = Assertions::instanceOf(
            EmitTypes::class,
            $dependencies[EmitTypes::class] ?? null,
        );
    }
}