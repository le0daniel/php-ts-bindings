<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Utils\ErrorTypescript;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Override;

final readonly class EmitTypes implements GeneratesLibFiles
{
    private const string TYPES_FILE = 'types';

    /**
     * Declarations this file always contains. An alias claiming one of these names would generate
     * a second, conflicting declaration right next to them.
     *
     * The error envelopes are asked of the catalogue rather than copied out of it: a branch added
     * there and forgotten here is a user alias free to shadow it.
     *
     * @return list<string>
     */
    private static function reservedAliases(): array
    {
        return [
            'Brand',
            'Success',
            'Failure',
            'Result',
            'OperationNamespaces',
            ...ErrorTypescript::envelopeNames(),
        ];
    }

    /**
     * Every declaration above lives in this file, so importing one is asking here for it. Not
     * static: a generator can only reach this through a dependency it declared, which is what makes
     * an import of a file no registered generator writes impossible.
     *
     * @param  list<string>  $values
     * @param  list<string>  $types
     */
    public function importFromTypes(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::TYPES_FILE),
            values: $values,
            types: $types,
        );
    }

    /**
     * @return array<string, TypescriptFile>
     */
    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        $reserved = self::reservedAliases();
        foreach ($registry->usedAliases() as $alias) {
            if (in_array($alias, $reserved, true)) {
                throw UnsupportedTypeException::reservedAlias($alias);
            }
        }

        /** @var list<string> $uniqueNamespaces */
        $uniqueNamespaces = [];
        foreach ($operations as $operation) {
            $namespace = $operation->operation->definition->namespace;
            if (! in_array($namespace, $uniqueNamespaces, true)) {
                $uniqueNamespaces[] = $namespace;
            }
        }

        // Declared here and referenced everywhere else: Failure names these rather than restating
        // their shapes, and only this file resolves the names.
        $errorEnvelopes = ErrorTypescript::envelopeDeclarations();

        // Which of them Failure is a union of depends on how this server maps exceptions onto them,
        // which is why it is emitted per run rather than written out here.
        $failureUnion = ErrorTypescript::failureUnion($metadata->configuration);
        $domainTypeParameter = ErrorTypescript::DOMAIN_TYPE_PARAMETER;
        $noDomainTypes = ErrorTypescript::NO_DOMAIN_TYPES;

        // The shared registry holds every alias any pass produced; the types file declares them
        // all, so every operation file can import any key of its own definitions' registries.
        $aliasTypeString = implode("\n", Arrays::mapWithKeys(
            $registry->toArray(),
            fn (string $alias, string $definition): string => "export type {$alias} = {$definition}",
        ));

        return [
            self::TYPES_FILE => new TypescriptFile(<<<TypeScript
export type OperationNamespaces = {$this->generateNamespaceUnion($uniqueNamespaces)};

/*
 * The finite error catalogue. Every failure is one of these, which is why Failure below is their
 * union rather than a hole for one. DomainError is the only branch whose payload varies per
 * operation - the names that operation exposed - and the only one declared conditionally: on
 * `{$noDomainTypes}` it collapses, so an operation exposing nothing has no 400 branch to narrow to.
 */
{$errorEnvelopes}

export type Success<T> = {success: true, data: T, __client?: unknown, __metadata?: Record<string, unknown>}
export type Failure<{$domainTypeParameter} extends string = {$noDomainTypes}> = {success: false, __metadata?: Record<string, unknown>} & ({$failureUnion});
export type Result<T, {$domainTypeParameter} extends string = {$noDomainTypes}> = Success<T> | Failure<{$domainTypeParameter}>;

declare const __brand: unique symbol;
export type Brand<TBrand extends string> = {readonly [__brand]: TBrand;};

/* All branded and named types exported */
{$aliasTypeString}
TypeScript),
        ];
    }

    /**
     * @param  list<string>  $namespaces
     */
    private function generateNamespaceUnion(array $namespaces): string
    {
        return implode('|', array_map(fn (string $namespace) => "'$namespace'", $namespaces));
    }
}
