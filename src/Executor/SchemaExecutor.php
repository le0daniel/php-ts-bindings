<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;

final class SchemaExecutor
{
    public function parse(NodeInterface $node, mixed $input, ParsingOptions $options = new ParsingOptions()): Success|Failure
    {
        $context = new Context();
        $result = $this->executeParse($node, $input, $context);

        if ($result === Value::INVALID) {
            return new Failure();
        }

        return new Success($result);
    }

    public function serialize(NodeInterface $node, mixed $input, SerializationOptions $options = new SerializationOptions()): Success|Failure
    {
        return new Failure();
    }

    private function executeParse(NodeInterface $node, mixed $input, Context $context): mixed
    {
        if ($node instanceof ConstraintNode) {
            $constrainedValue = $this->executeParse($node->node, $input, $context);
            if ($constrainedValue === Value::INVALID || !$node->areConstraintsFulfilled($constrainedValue, null)) {
                return Value::INVALID;
            }
            return $constrainedValue;
        }

        if ($node instanceof CustomCastingNode) {
            $arrayValue = $this->executeParse($node->node, $input, $context);
            if ($arrayValue === Value::INVALID || !is_array($arrayValue)) {
                return Value::INVALID;
            }
            return $node->cast($arrayValue);
        }

        return match (true) {
            $node instanceof LeafNode => $node->parseValue($input, $context),
            $node instanceof UnionNode => $this->parseUnion($node, $input, $context),
            $node instanceof StructNode => $this->parseStruct($node, $input, $context),
            $node instanceof TupleNode => $this->parseTuple($node, $input, $context),
            $node instanceof ListNode => $this->parseList($node, $input, $context),
            default => Value::INVALID,
        };
    }

    private function parseList(ListNode $node, mixed $input, Context $context): array|Value
    {
        if (!is_array($input) || !array_is_list($input)) {
            return Value::INVALID;
        }

        if (empty($input)) {
            return [];
        }

        $list = [];
        $index = 0;

        foreach ($input as $item) {
            $context->enterPath($index);
            $result = $this->executeParse($node->type, $item, $context);
            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $list[] = $result;

            $context->leavePath();
            $index++;
        }
        return $list;
    }

    private function parseUnion(UnionNode $node, mixed $input, Context $context): mixed
    {
        if ($input === null && $node->acceptsNull) {
            return null;
        }

        if ($node->isDiscriminated()) {
            $discriminatedType = $node->getDiscriminatedType($this->extractKeyedInputValue($node->discriminator, $input));
            if ($discriminatedType) {
                return $this->executeParse($discriminatedType, $input, $context);
            }
            return Value::INVALID;
        }

        // ToDo Handle probing context.
        foreach ($node->types as $type) {
            $result = $this->executeParse($type, $input, $context);
            if ($result !== Value::INVALID) {
                return $result;
            }
        }
        return Value::INVALID;
    }

    private function parseStruct(StructNode $node, mixed $input, Context $context): array|object
    {
        if (!is_array($input) || array_is_list($input)) {
            return Value::INVALID;
        }

        $struct = [];
        foreach ($node->properties as $propertyNode) {
            if (!$propertyNode->propertyType->isInput()) {
                continue;
            }

            $context->enterPath($propertyNode->name);
            $propertyValue = $this->extractKeyedInputValue($propertyNode->name, $input);

            if ($propertyValue === Value::UNDEFINED) {
                if ($propertyNode->isOptional) {
                    continue;
                } else {
                    return Value::INVALID;
                }
            }

            $result = $this->executeParse(
                $propertyNode->type,
                Value::toNull($propertyValue),
                $context,
            );
            $context->leavePath();

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
    private function parseTuple(TupleNode $node, mixed $input, Context $context): Value|array
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
            $context->enterPath($index);
            $result = $this->executeParse($type, $input[$index], $context);
            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $tupleValues[] = $result;
            $context->leavePath();
        }
        return $tupleValues;
    }

    private function extractKeyedInputValue(string $key, array|object $input): mixed
    {
        if (is_array($input)) {
            return array_key_exists($key, $input) ? $input[$key] : Value::UNDEFINED;
        }

        if ($input instanceof ArrayAccess) {
            return $input->offsetExists($key)
                ? $input[$key]
                : Value::UNDEFINED;
        }

        return property_exists($input, $key)
            ? $input->{$key}
            : Value::UNDEFINED;
    }
}