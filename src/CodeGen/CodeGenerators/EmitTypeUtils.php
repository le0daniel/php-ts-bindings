<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\TypedOperation;

final class EmitTypeUtils implements GeneratesLibFiles, DependsOn
{
    public function dependsOnGenerator(): array
    {
        return [
            EmitTypes::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function emitFiles(array $operations, ServerMetadata $metadata): array
    {
        $queryNamespaces = array_reduce($operations, function (array $carry, TypedOperation $operation) {
            if ($operation->operation->definition->type !== 'query') {
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

        return [
            "utils" => <<<TypeScript
import type {SPAClientDirectives, WithClientDirectives} from "./types";

type QueryNamespaces = {$this->generateLiteralUnion($queryNamespaces)};

export function queryKey(ns: QueryNamespaces, ...args: unknown[]): [string, ...unknown[]] {
    return [ns, ...args];
}

export function isSpaClientDirectives<const T>(result: WithClientDirectives<T>): result is SPAClientDirectives<T> {
    if (!result.__client || typeof result.__client !== 'object') {
        return false;
    }

    return "type" in result.__client && result.__client.type === "operations-spa";
}
TypeScript
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