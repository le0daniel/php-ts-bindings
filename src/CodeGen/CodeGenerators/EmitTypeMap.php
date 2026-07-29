<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;

final class EmitTypeMap implements GeneratesLibFiles
{

    public function emitFiles(array $operations, ServerMetadata $metadata, TypeRegistry $registry): array
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

        return [
            'types' => new TypescriptFile(<<<TypeScript
/**
 * Full type map of all operations, input and output types.
 */
export type TYPE_MAP = {$mapAsTsTypeString};
TypeScript
            )
        ];
    }
}