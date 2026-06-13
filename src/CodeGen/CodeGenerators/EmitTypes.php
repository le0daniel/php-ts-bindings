<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use RuntimeException;

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

        $brands = array_reduce($operations, function (array $carry, TypedOperation $operation) {
            $inputBrands = $this->collectBrandedTypes($operation->operation->inputNode(), DefinitionTarget::INPUT);
            $outputBrands = $this->collectBrandedTypes($operation->operation->outputNode(), DefinitionTarget::OUTPUT);
            return $this->mergeBrandedTypes($carry, $inputBrands, $outputBrands);
        }, []);

        $brandedTypeStrings = Arrays::mapWithKeys(
            $brands,
            function (string $brandName, string $type): string {
                $capitalizedBrandName = ucfirst($brandName);
                $encodedBrandName = json_encode($brandName, JSON_THROW_ON_ERROR);
                return "export type {$capitalizedBrandName} = {$type} & Brand<{$encodedBrandName}>";
            });

        $brandedTypeString = implode("\n", $brandedTypeStrings);

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
     * @return array<string, string>
     */
    private function collectBrandedTypes(NodeInterface $ast, DefinitionTarget $target): array
    {
        /** @var BuiltInNode[] $brandedNodes */
        $brandedNodes = [];

        $stack = [
            $ast,
        ];

        while ($current = array_pop($stack)) {
            if ($current instanceof ValidatableNode) {
                $current->validate();
            }

            if ($current instanceof LeafNode) {
                if ($current instanceof BuiltInNode && $current->brand !== null) {
                    $brandedNodes[] = $current;
                }

                continue;
            }

            match ($current::class) {
                ConstraintNode::class, CustomCastingNode::class, ListNode::class, NamedNode::class, PropertyNode::class, RecordNode::class => $stack[] = $current->node,
                TupleNode::class, IntersectionNode::class, UnionNode::class => array_push($stack, ...$current->types),
                StructNode::class => array_push($stack, ... $current->properties),
                default => throw new RuntimeException("Unexpected node: " . $current::class),
            };
        }

        $brandedTypes = [];
        foreach ($brandedNodes as $node) {
            $typeDefinition = $target === DefinitionTarget::INPUT
                ? $node->inputDefinition()
                : $node->outputDefinition();

            if (!isset($brandedTypes[$node->brand])) {
                $brandedTypes[$node->brand] = $typeDefinition;
                continue;
            }

            if ($typeDefinition !== $brandedTypes[$node->brand]) {
                throw new RuntimeException("Branded type {$node->brand} has different definitions");
            }
        }

        return $brandedTypes;
    }

    /**
     * @param array<string, string> $brands
     * @param array<string, string> ...$otherTypes
     * @return array<string, string>
     */
    private function mergeBrandedTypes(array $brands, array ... $otherTypes): array
    {
        foreach ($otherTypes as $keyValuePairs) {
            foreach ($keyValuePairs as $key => $value) {
                if (!isset($brands[$key])) {
                    $brands[$key] = $value;
                    continue;
                }
                if ($brands[$key] !== $value) {
                    throw new RuntimeException("Branded type {$key} has different definitions");
                }
            }
        }
        return $brands;
    }
}
