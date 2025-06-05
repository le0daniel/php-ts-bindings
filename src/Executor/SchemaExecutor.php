<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor;

use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Parser\Nodes\OptionalType;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructType;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleType;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionType;

final class SchemaExecutor
{
    public function parse(NodeInterface $node, mixed $input): mixed
    {
        return match (true) {
            $node instanceof LeafType => $node->parseValue($input, null),
            $node instanceof UnionType => $this->parseUnion($node, $input),
            $node instanceof StructType => $this->parseStruct($node, $input),
            $node instanceof TupleType => $this->parseTuple($node, $input),
            default => Value::INVALID,
        };
    }

    private function parseUnion(UnionType $node, mixed $input): mixed
    {
        if ($node->isDiscriminated()) {
            $discriminatedType = $node->getDiscriminatedType($this->extractKeyedInputValue($node->discriminator, $input));
            if ($discriminatedType) {
                return $this->parse($discriminatedType, $input);
            }
            return Value::INVALID;
        }

        foreach ($node->types as $type) {
            $result = $this->parse($type, $input);
            if ($result !== Value::INVALID) {
                return $result;
            }
        }
        return Value::INVALID;
    }

    private function parseStruct(StructType $node, mixed $input): array|object
    {
        if (!is_array($input) || array_is_list($input)) {
            return Value::INVALID;
        }

        $struct = [];
        foreach ($node->properties as $name => $propertyType) {
            $isOptional = $propertyType instanceof OptionalType;
            $propertyValue = $this->extractKeyedInputValue($name, $input);

            if ($propertyValue === Value::UNDEFINED) {
                if ($isOptional) {
                    continue;
                } else {
                    return Value::INVALID;
                }
            }

            $result = $this->parse(
                $propertyType instanceof OptionalType
                    ? $propertyType->node
                    : $propertyType,
                $propertyValue
            );

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $struct[$name] = $result;
        }

        return $node->phpType->coerceFromArray($struct);
    }

    private function parseTuple(TupleType $node, mixed $input): Value|array
    {
        if (!is_array($input) || !array_is_list($input)) {
            return Value::INVALID;
        }

        $expectedCount = count($node->types);
        if (count($input) !== $expectedCount) {
            return Value::INVALID;
        }

        $tupleValues = [];
        foreach ($node->types as $index => $type) {
            $result = $this->parse($type, $input[$index]);
            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $tupleValues[] = $result;
        }
        return $tupleValues;
    }

    private function extractKeyedInputValue(string $key, array $input): mixed
    {
        return array_key_exists($key, $input) ? $input[$key] : Value::UNDEFINED;
    }
}