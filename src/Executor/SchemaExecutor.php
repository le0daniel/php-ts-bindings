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
        if ($node instanceof LeafType) {
            return $node->parseValue($input, null);
        }

        // ToDo: Unions are not really efficient.
        if ($node instanceof UnionType) {
            foreach ($node->types as $type) {
                $result = $this->parse($type, $input);
                if ($result !== Value::INVALID) {
                    return $result;
                }
            }
            return Value::INVALID;
        }

        if ($node instanceof StructType) {
            if (!is_array($input) || array_is_list($input)) {
                return Value::INVALID;
            }

            $struct = [];
            foreach ($node->properties as $name => $propertyType) {
                $propertyValue = $this->extractKeyedInputValue($name, $input);
                if ($propertyValue === Value::UNDEFINED && $propertyType instanceof OptionalType) {
                    continue;
                }

                $result = $propertyType instanceof OptionalType
                    ? $this->parse($propertyType->node, $propertyValue)
                    : $this->parse($propertyType, $propertyValue);

                if ($result === Value::INVALID) {
                    return Value::INVALID;
                }
                $struct[$name] = $result;
            }
            return $node->phpType->coerceFromArray($struct);
        }

        if ($node instanceof TupleType) {
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

        return Value::INVALID;
    }

    private function extractKeyedInputValue(string $key, array $input): mixed
    {
        return array_key_exists($key, $input) ? $input[$key] : Value::UNDEFINED;
    }

    public function serialize(NodeInterface $node, mixed $input): mixed
    {

    }
}