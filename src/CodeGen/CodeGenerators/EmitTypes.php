<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Utils\Arrays;

final class EmitTypes implements GeneratesLibFiles
{
    /**
     * Declarations this file always contains. An alias claiming one of these names would generate
     * a second, conflicting declaration right next to them.
     */
    private const array RESERVED_ALIASES = [
        'Brand',
        'Success',
        'Failure',
        'Result',
        'OperationNamespaces',
        'WithClientDirectives',
        'SPAClientDirectives',
        'TYPE_MAP',
    ];

    /**
     * @return array<string, TypescriptFile>
     */
    public function emitFiles(array $operations, ServerMetadata $metadata, TypeRegistry $registry): array
    {
        foreach ($registry->usedAliases() as $alias) {
            if (in_array($alias, self::RESERVED_ALIASES, true)) {
                throw UnsupportedTypeException::reservedAlias($alias);
            }
        }

        $uniqueNamespaces = array_reduce($operations, function (array $carry, TypedOperation $operation) {
            if (!in_array($operation->operation->definition->namespace, $carry, true)) {
                return [
                    ...$carry,
                    $operation->operation->definition->namespace,
                ];
            }
            return $carry;
        }, []);

        // The shared registry holds every alias any pass produced; the types file declares them
        // all, so every operation file can import any key of its own definitions' registries.
        $aliasTypeString = implode("\n", Arrays::mapWithKeys(
            $registry->toArray(),
            fn(string $alias, string $definition): string => "export type {$alias} = {$definition}",
        ));

        return [
            "types" => new TypescriptFile(<<<TypeScript
export type OperationNamespaces = {$this->generateNamespaceUnion($uniqueNamespaces)};

export type Success<T> = {success: true, data: T}
export type Failure<E extends {code: number}> = {success: false} & E;
export type Result<T, E extends {code: number} = never> = Success<T> | Failure<E>;
export type WithClientDirectives<T> = T & {__client?: unknown}
export type SPAClientDirectives<T> = T & {
    __client: {
        type: "operations-spa",
        redirect?: {type: "soft"|"hard"; url: string;},
        toasts?: {type: 'success'|'error'|'alert'|'info', message: string;}[],
        invalidations?: [string, string, ...unknown[]][]
    }
};

declare const __brand: unique symbol;
export type Brand<TBrand extends string> = {readonly [__brand]: TBrand;};

/* All branded and named types exported */
{$aliasTypeString}
TypeScript),
        ];
    }

    /**
     * @param list<string> $namespaces
     * @return string
     */
    private function generateNamespaceUnion(array $namespaces): string
    {
        return implode("|", array_map(fn(string $namespace) => "'$namespace'", $namespaces));
    }
}
