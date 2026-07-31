<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Override;

final readonly class EmitTypes implements GeneratesLibFiles
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
        'ClientDirectives',
        'ClientToast',
        'ClientRedirect',
        'ClientInvalidation',
        'TYPE_MAP',
    ];

    /**
     * @return array<string, TypescriptFile>
     */
    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        foreach ($registry->usedAliases() as $alias) {
            if (in_array($alias, self::RESERVED_ALIASES, true)) {
                throw UnsupportedTypeException::reservedAlias($alias);
            }
        }

        /** @var list<string> $uniqueNamespaces */
        $uniqueNamespaces = [];
        foreach ($operations as $operation) {
            $namespace = $operation->operation->definition->namespace;
            if (!in_array($namespace, $uniqueNamespaces, true)) {
                $uniqueNamespaces[] = $namespace;
            }
        }

        // The shared registry holds every alias any pass produced; the types file declares them
        // all, so every operation file can import any key of its own definitions' registries.
        $aliasTypeString = implode("\n", Arrays::mapWithKeys(
            $registry->toArray(),
            fn(string $alias, string $definition): string => "export type {$alias} = {$definition}",
        ));

        // Derived from the enum so the emitted union can never drift from what Client::toast accepts.
        $toastTypes = implode('|', array_map(
            fn(ToastType $type): string => "'{$type->value}'",
            ToastType::cases(),
        ));

        return [
            "types" => new TypescriptFile(<<<TypeScript
export type OperationNamespaces = {$this->generateNamespaceUnion($uniqueNamespaces)};

export type Success<T> = {success: true, data: T}
export type Failure<E extends {code: number}> = {success: false} & E;
export type Result<T, E extends {code: number} = never> = Success<T> | Failure<E>;
export type ClientToast = {type: {$toastTypes}; message: string;};
export type ClientRedirect = {url: string; reload: boolean;};
export type ClientInvalidation = [string, ...unknown[]];
export type ClientDirectives = {
    type: "operations-spa";
    redirect?: ClientRedirect;
    toasts?: ClientToast[];
    invalidations?: ClientInvalidation[];
};
export type WithClientDirectives<T> = T & {__client?: unknown}
export type SPAClientDirectives<T> = T & {__client: ClientDirectives};

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
