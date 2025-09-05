<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\OperationData;
use Le0daniel\PhpTsBindings\Utils\Arrays;

final class EmitTypeMap implements GeneratesLibFiles
{

    public function emitFiles(array $operations, GeneralMetadata $metadata): array
    {
        /**
         * @var array<"query"|"command", array<string, array{input: string, output: string, errors: string}>> $map
         */
        $map = array_reduce($operations, function (array $carry, OperationData $operation): array {
            $carry[$operation->definition->type][$operation->definition->fullyQualifiedName()] = [
                'input' => $operation->inputDefinition,
                'output' => $operation->outputDefinition,
                'errors' => $operation->errorDefinition,
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
            'types' => new TsFile(content: <<<TypeScript

/**
 * Full type map of all operations, input and output types.
 */
export type TYPE_MAP = {$mapAsTsTypeString};
TypeScript
            )
        ];
    }
}