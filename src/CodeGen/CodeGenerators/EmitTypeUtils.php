<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * Not readonly: the EmitTypes whose directive types the guards narrow to is injected after
 * construction, which is the only way it can be the same instance the generator runs.
 */
final class EmitTypeUtils implements GeneratesLibFiles, DependsOn
{
    private const string UTILS_FILE = 'utils';

    private EmitTypes $types;

    /**
     * Not static: reaching this means declaring the dependency, and a declared dependency that is
     * not registered fails the run before a line is generated.
     *
     * @param list<string> $values
     * @param list<string> $types
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
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->types = Assertions::instanceOf(
            EmitTypes::class,
            $dependencies[EmitTypes::class] ?? null,
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
            if (!in_array($namespace, $queryNamespaces, true)) {
                $queryNamespaces[] = $namespace;
            }
        }

        // Derived from the enum, so the values the guard accepts can never drift from ToastType.
        $toastTypes = implode(', ', array_map(
            fn(ToastType $type): string => "'{$type->value}'",
            ToastType::cases(),
        ));

        return [
            self::UTILS_FILE => new TypescriptFile(<<<TypeScript
type QueryNamespaces = {$this->generateLiteralUnion($queryNamespaces)};

const TOAST_TYPES = [{$toastTypes}] as const;

export function queryKey(ns: QueryNamespaces, ...args: unknown[]): [string, ...unknown[]] {
    return [ns, ...args];
}

function isArrayOf<V>(value: unknown, predicate: (item: unknown) => item is V): value is V[] {
    return Array.isArray(value) && value.every(predicate);
}

export function isClientToast(value: unknown): value is ClientToast {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const toast = value as Partial<ClientToast>;
    return typeof toast.message === 'string'
        && typeof toast.type === 'string'
        && (TOAST_TYPES as readonly string[]).includes(toast.type);
}

export function isClientRedirect(value: unknown): value is ClientRedirect {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const redirect = value as Partial<ClientRedirect>;
    return typeof redirect.url === 'string' && typeof redirect.reload === 'boolean';
}

function isClientInvalidation(value: unknown): value is [string, ...unknown[]] {
    return Array.isArray(value) && typeof value[0] === 'string';
}

/**
 * Narrows to the full directive payload, so it verifies every directive it claims and not just
 * the discriminator: a server on an older format would otherwise be narrowed to a shape it does
 * not have. Unknown directive keys are ignored, adding one stays backwards compatible.
 */
export function isSpaClientDirectives<const T>(result: WithClientDirectives<T>): result is SPAClientDirectives<T> {
    if (!result.__client || typeof result.__client !== 'object') {
        return false;
    }

    const directives = result.__client as Partial<ClientDirectives>;
    if (directives.type !== 'operations-spa') {
        return false;
    }

    return (directives.redirect === undefined || isClientRedirect(directives.redirect))
        && (directives.toasts === undefined || isArrayOf(directives.toasts, isClientToast))
        && (directives.invalidations === undefined || isArrayOf(directives.invalidations, isClientInvalidation));
}
TypeScript, [
                $this->types->importFromTypes(types: [
                    'ClientDirectives',
                    'ClientRedirect',
                    'ClientToast',
                    'SPAClientDirectives',
                    'WithClientDirectives',
                ]),
            ])
        ];
    }

    /**
     * @param list<string> $namespaces
     * @return string
     */
    private function generateLiteralUnion(array $namespaces): string
    {
        return implode("|", array_map(fn(string $namespace) => "'$namespace'", $namespaces));
    }
}