<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * The helpers an application reaches for that belong to no single transport: a cache key, and the
 * assertion that turns a failed envelope into a throw.
 *
 * Not readonly: the generators declaring the envelope it narrows and the exception it throws are
 * injected after construction, which is the only way they can be the same instances the generator
 * runs.
 */
final class EmitTypeUtils implements DependsOn, GeneratesLibFiles
{
    private const string UTILS_FILE = 'utils';

    private EmitTypes $types;

    private EmitOperationClientBindings $bindings;

    /**
     * Not static: reaching this means declaring the dependency, and a declared dependency that is
     * not registered fails the run before a line is generated.
     *
     * @param  list<string>  $values
     * @param  list<string>  $types
     */
    public function importFromUtils(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::UTILS_FILE),
            values: $values,
            types: $types,
        );
    }

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitTypes::class,
            EmitOperationClientBindings::class,
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->types = Assertions::instanceOf(
            EmitTypes::class,
            $dependencies[EmitTypes::class] ?? null,
        );
        $this->bindings = Assertions::instanceOf(
            EmitOperationClientBindings::class,
            $dependencies[EmitOperationClientBindings::class] ?? null,
        );
    }

    /**
     * @return array<string, TypescriptFile>
     */
    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        /** @var list<string> $queryNamespaces */
        $queryNamespaces = [];
        foreach ($operations as $operation) {
            if ($operation->operation->definition->type !== OperationType::QUERY) {
                continue;
            }

            $namespace = $operation->operation->definition->namespace;
            if (! in_array($namespace, $queryNamespaces, true)) {
                $queryNamespaces[] = $namespace;
            }
        }

        return [
            self::UTILS_FILE => new TypescriptFile(<<<TypeScript
type QueryNamespaces = {$this->generateLiteralUnion($queryNamespaces)};

export function queryKey(ns: QueryNamespaces, ...args: unknown[]): [string, ...unknown[]] {
    return [ns, ...args];
}

/**
 * Narrows a Result to its success branch, throwing otherwise, for call sites that would rather
 * catch than branch.
 *
 * The error union is deliberately not inferred here: a catch clause variable is `unknown` in
 * TypeScript whatever was thrown, so no signature on this function could carry E to the catch.
 * Name it there instead - `OperationException.is<ProductError>(e)` types `e.cause` for you.
 */
export function throwOnFailure<const T>(result: Result<T, any>): asserts result is Success<T> {
    if (!result.success) {
        throw new OperationException(result);
    }
}
TypeScript, [
                $this->types->importFromTypes(types: ['Result', 'Success']),
                // Constructed, not just annotated: a type only import would leave
                // `new OperationException(...)` referencing nothing at runtime.
                $this->bindings->importFromOperationException(values: ['OperationException']),
            ]),
        ];
    }

    /**
     * @param  list<string>  $namespaces
     */
    private function generateLiteralUnion(array $namespaces): string
    {
        return implode('|', array_map(fn (string $namespace) => "'$namespace'", $namespaces));
    }
}
