<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;

final class EmitTypes implements GeneratesLibFiles
{

    /**
     * @return array<string, string>
     */
    public function emitFiles(array $operations, ServerMetadata $metadata): array
    {
        $uniqueNamespaces = array_reduce($operations, function (array $carry, TypedOperation $operation) {
            if (!in_array($operation->operation->definition->namespace, $carry, true)) {
                return [
                    ...$carry,
                    $operation->operation->definition->namespace,
                ];
            }
            return $carry;
        }, []);

        $brandedTypeString = implode("\n", Arrays::mapWithKeys(
            $this->collectAliases($operations),
            fn(string $alias, string $definition): string => "export type {$alias} = {$definition}",
        ));

        return [
            "types" => <<<TypeScript
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

/* All Branded types exported */
{$brandedTypeString}

TypeScript,
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

    /**
     * Every alias referenced anywhere in the server, sorted by name. Each operation brings the
     * aliases its own types refer to; the same alias standing for two different types across
     * operations would generate contradicting declarations and is rejected by the registry.
     *
     * @param list<TypedOperation> $operations
     * @return array<string, string>
     */
    private function collectAliases(array $operations): array
    {
        $registry = new TypeRegistry();

        foreach ($operations as $operation) {
            foreach ($operation->registry->toArray() as $alias => $definition) {
                $registry->set($alias, $definition);
            }
        }

        return $registry->toArray();
    }
}
