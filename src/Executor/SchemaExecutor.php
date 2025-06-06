<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor;

use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;

final class SchemaExecutor
{
    public function parse(NodeInterface $node, mixed $input): mixed
    {
        if ($node instanceof ConstraintNode) {
            $constrainedValue = $this->parse($node->node, $input);
            if ($constrainedValue === Value::INVALID || !$node->areConstraintsFulfilled($constrainedValue, null)) {
                return Value::INVALID;
            }
            return $constrainedValue;
        }

        return match (true) {
            $node instanceof LeafType => $node->parseValue($input, null),
            $node instanceof UnionNode => $this->parseUnion($node, $input),
            $node instanceof StructNode => $this->parseStruct($node, $input),
            $node instanceof TupleNode => $this->parseTuple($node, $input),
            default => Value::INVALID,
        };
    }

    private function parseUnion(UnionNode $node, mixed $input): mixed
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

    private function parseStruct(StructNode $node, mixed $input): array|object
    {
        if (!is_array($input) || array_is_list($input)) {
            return Value::INVALID;
        }

        $struct = [];
        foreach ($node->properties as $propertyNode) {
            $propertyValue = $this->extractKeyedInputValue($propertyNode->name, $input);

            if ($propertyValue === Value::UNDEFINED) {
                if ($propertyNode->isOptional) {
                    continue;
                } else {
                    return Value::INVALID;
                }
            }

            $result = $this->parse(
                Value::toNull($propertyValue),
                $propertyValue
            );

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }

            $struct[$propertyNode->name] = $result;
        }

        return $node->phpType->coerceFromArray($struct);
    }

    /**
     * @param TupleNode $node
     * @param mixed $input
     * @return (Value::INVALID)|list<mixed>
     */
    private function parseTuple(TupleNode $node, mixed $input): Value|array
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