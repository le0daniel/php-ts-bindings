<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;

final class EmitTypeUtils implements GeneratesLibFiles, DependsOn
{
    public function dependsOnGenerator(): array
    {
        return [
            EmitTypes::class,
        ];
    }

    /**
     * @return array<string, TypescriptFile>
     */
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        $queryNamespaces = array_reduce($operations, function (array $carry, TypedOperation $operation) {
            if ($operation->operation->definition->type !== OperationType::QUERY) {
                return $carry;
            }

            if (!in_array($operation->operation->definition->namespace, $carry, true)) {
                return [
                    ...$carry,
                    $operation->operation->definition->namespace,
                ];
            }
            return $carry;
        }, []);

        // Derived from the enum, so the values the guard accepts can never drift from ToastType.
        $toastTypes = implode(', ', array_map(
            fn(ToastType $type): string => "'{$type->value}'",
            ToastType::cases(),
        ));

        return [
            "utils" => new TypescriptFile(<<<TypeScript
import type {ClientDirectives, ClientRedirect, ClientToast, SPAClientDirectives, WithClientDirectives} from "./types";

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
TypeScript)
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