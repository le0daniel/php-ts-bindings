<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\BrandedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ObjectCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
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

        [$brandedInts, $brandedStrings] = array_reduce($operations, function (array $carry, TypedOperation $operation) {
            [$brandedInts, $brandedStrings] = $carry;
            [$inputBrandedInts, $inputBrandedStrings] = $this->collectBrandedTypes($operation->operation->inputNode());
            [$outputBrandedInts, $outputBrandedStrings] = $this->collectBrandedTypes($operation->operation->outputNode());

            return [
                [... $brandedInts, ...$inputBrandedInts, ...$outputBrandedInts],
                [... $brandedStrings, ...$inputBrandedStrings, ...$outputBrandedStrings],
            ];
        }, [[], []]);

        $uniqueBrandedInts = array_values(array_unique($brandedInts));
        $uniqueBrandedStrings = array_values(array_unique($brandedStrings));

        $brandedIntTypes = implode(
            PHP_EOL,
            array_map(fn(string $brandValue) => $this->toBrandedType($brandValue, "number"), $uniqueBrandedInts)
        );

        $brandedStringType = implode(
            PHP_EOL,
            array_map(fn(string $brandValue) => $this->toBrandedType($brandValue, "string"), $uniqueBrandedStrings)
        );

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
export type Branded<T, TBrand extends string> = T & {readonly [__brand]: TBrand;};

/* All Branded types exported */
{$brandedIntTypes}
{$brandedStringType}

TypeScript,
        ];
    }

    /**
     * @param string $brandValue
     * @param "string"|"number" $type
     * @return string
     */
    private function toBrandedType(string $brandValue, string $type): string
    {
        $typeName = ucfirst($brandValue);
        $encodedBrandValue = json_encode($brandValue, JSON_THROW_ON_ERROR);
        return "export type {$typeName} = Branded<{$type}, {$encodedBrandValue}>;";
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
     * @return array{list<string>, list<string>}
     */
    private function collectBrandedTypes(NodeInterface $ast): array
    {
        /** @var BrandedNode[] $brandedNodes */
        $brandedNodes = [];

        $stack = [
            $ast,
        ];

        while ($current = array_pop($stack)) {
            if ($current instanceof ValidatableNode) {
                $current->validate();
            }

            if ($current instanceof BrandedNode) {
                $brandedNodes[] = $current;
                continue;
            }

            match ($current::class) {
                ConstraintNode::class, ObjectCastingNode::class, ListNode::class, PropertyNode::class, RecordNode::class => $stack[] = $current->node,
                TupleNode::class, IntersectionNode::class, UnionNode::class => array_push($stack, ...$current->types),
                StructNode::class => array_push($stack, ... $current->properties),
                default => throw new RuntimeException("Unexpected node: " . $current::class),
            };
        }

        $brandedInts = [];
        $brandedStrings = [];

        foreach ($brandedNodes as $brandedNode) {
            $type = $brandedNode->node;
            if (!$type instanceof BuiltInNode) {
                throw new RuntimeException("Unexpected BuiltInNode for a Branded Node: " . $type::class);
            }

            if ($type->type === BuiltInType::STRING) {
                $brandedStrings[] = $brandedNode->brand;
                continue;
            }

            if ($type->type === BuiltInType::INT) {
                $brandedInts[] = $brandedNode->brand;
                continue;
            }

            throw new RuntimeException("Unexpected branded node type: " . $type->type->name);
        }

        return [
            $brandedInts,
            $brandedStrings
        ];
    }
}