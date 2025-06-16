<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use stdClass;

final class SchemaExecutor
{
    public function parse(NodeInterface $node, mixed $input, ParsingOptions $options = new ParsingOptions()): Success|Failure
    {
        $context = new Context();
        $result = $this->executeParse($node, $input, $context);

        if ($result === Value::INVALID) {
            return new Failure($context->issues);
        }

        return new Success($result);
    }

    public function serialize(NodeInterface $node, mixed $output, SerializationOptions $options = new SerializationOptions()): Success|Failure
    {
        $context = new Context();
        $result = $this->executeSerialize($node, $output, $context);

        if ($result === Value::INVALID) {
            return new Failure($context->issues);
        }

        return new Success($result);
    }

    private function executeSerialize(NodeInterface $node, mixed $output, Context $context): mixed
    {
        // Constraints are ignored when serializing.
        if ($node instanceof ConstraintNode) {
            return $this->executeSerialize($node->node, $output, $context);
        }

        // ToDo: Implement custom casting node correctly.
        if ($node instanceof CustomCastingNode) {
            $object = $this->executeSerialize($node->node, $output, $context);

            if ($object === Value::INVALID) {
                return Value::INVALID;
            }

            if (!$object instanceof stdClass) {
                $objectClass = get_class($object);
                $context->addIssue(new Issue(
                    'validation.invalid_cast',
                    [
                        "message" => "Failed to serialize object($objectClass) to standard class.",
                        "value" => $output,
                        "serializedValue" => $object,
                    ]
                ));
                return Value::INVALID;
            }

            return $object;
        }

        $serializedValue = match (true) {
            $node instanceof NamedNode => $this->executeSerialize($node->node, $output, $context),
            $node instanceof LeafNode => $node->serializeValue($output, $context),
            $node instanceof UnionNode => $this->serializeUnion($node, $output, $context),
            $node instanceof RecordNode => $this->serializeRecord($node, $output, $context),
            $node instanceof StructNode => $this->serializeStruct($node, $output, $context),
            $node instanceof TupleNode => $this->serializeTuple($node, $output, $context),
            $node instanceof ListNode => $this->serializeList($node, $output, $context),
            default => Value::INVALID,
        };

        // Allow for catching errors at null boundaries during serialization.
        if ($serializedValue === Value::INVALID && $node instanceof UnionNode && $node->acceptsNull) {
            return null;
        }

        return $serializedValue;
    }

    private function serializeRecord(RecordNode $node, mixed $output, Context $context): object
    {
        if (!is_iterable($output)) {
            return Value::INVALID;
        }

        $values = [];

        foreach ($output as $key => $item) {
            if (!is_string($key)) {
                $context->addIssue(new Issue(
                    'validation.invalid_key_type',
                    [
                        'message' => 'Record keys must be strings, got: ' . gettype($key),
                        'keyValue' => $key,
                    ]
                ));
                return Value::INVALID;
            }

            $context->enterPath($key);
            $result = $this->executeSerialize($node->type, $item, $context);
            $context->leavePath();

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $values[$key] = $result;
        }
        return (object) $values;
    }

    /**
     * @param ListNode $node
     * @param mixed $output
     * @param Context $context
     * @return list<mixed>|Value
     */
    private function serializeList(ListNode $node, mixed $output, Context $context): array|Value
    {
        if (!is_iterable($output)) {
            return Value::INVALID;
        }

        $values = [];
        $index = 0;

        foreach ($output as $item) {
            $context->enterPath($index);
            $result = $this->executeSerialize($node->type, $item, $context);
            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $values[] = $result;
            $index++;
        }
        return $values;
    }

    /**
     * @param TupleNode $node
     * @param mixed $output
     * @param Context $context
     * @return list<mixed>|Value
     */
    private function serializeTuple(TupleNode $node, mixed $output, Context $context): array|Value
    {
        $tupleValues = [];
        foreach ($node->types as $index => $type) {
            $context->enterPath($index);
            $result = $this->executeSerialize($type, $output[$index], $context);
            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $tupleValues[] = $result;
            $context->leavePath();
        }
        return $tupleValues;
    }

    private function serializeStruct(StructNode $node, mixed $output, Context $context): object
    {
        $struct = [];
        foreach ($node->properties as $propertyNode) {
            if (!$propertyNode->propertyType->isOutput()) {
                continue;
            }

            $context->enterPath($propertyNode->name);
            $propertyValue = $this->extractKeyedValue($propertyNode->name, $output);

            if ($propertyValue === Value::INVALID) {
                $context->leavePath();
                return Value::INVALID;
            }

            if ($propertyValue === Value::UNDEFINED) {
                if ($propertyNode->isOptional) {
                    $context->leavePath();
                    continue;
                } else {
                    $context->addIssue(new Issue(
                        'validation.missing_property',
                        [
                            'message' => "Missing property: {$propertyNode->name}",
                        ]
                    ));
                    $context->leavePath();
                    return Value::INVALID;
                }
            }

            $result = $this->executeSerialize(
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
    
        return (object) $struct;
    }
    
    private function serializeUnion(UnionNode $node, mixed $output, Context $context): mixed
    {
        if ($output === null && $node->acceptsNull) {
            return null;
        }

        if ($node->isDiscriminated()) {
            $valueToCheck = $this->extractKeyedValue($node->discriminator, $output);
            if ($valueToCheck instanceof Value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => 'Invalid type for union discriminated type.',
                        'value' => $output,
                        'discriminator' => $node->discriminator,
                    ]
                ));
                return Value::INVALID;
            }

            $discriminatedType = $node->getDiscriminatedType($valueToCheck);
            if ($discriminatedType) {
                return $this->executeSerialize($discriminatedType, $output, $context);
            }
            return Value::INVALID;
        }

        foreach ($node->types as $type) {
            $result = $this->executeSerialize($type, $output, $context);
            if ($result !== Value::INVALID) {
                return $result; 
            }
        }
        return Value::INVALID;
    }

    private function executeParse(NodeInterface $node, mixed $input, Context $context): mixed
    {
        if ($node instanceof ConstraintNode) {
            $constrainedValue = $this->executeParse($node->node, $input, $context);
            if ($constrainedValue === Value::INVALID || !$node->areConstraintsFulfilled($constrainedValue, $context)) {
                return Value::INVALID;
            }
            return $constrainedValue;
        }

        if ($node instanceof CustomCastingNode) {
            $arrayValue = $this->executeParse($node->node, $input, $context);
            if ($arrayValue === Value::INVALID || !is_array($arrayValue)) {
                return Value::INVALID;
            }
            return $node->cast($arrayValue, $context);
        }

        return match (true) {
            $node instanceof NamedNode => $this->executeParse($node->node, $input, $context),
            $node instanceof LeafNode => $node->parseValue($input, $context),
            $node instanceof RecordNode => $this->parseRecord($node, $input, $context),
            $node instanceof UnionNode => $this->parseUnion($node, $input, $context),
            $node instanceof StructNode => $this->parseStruct($node, $input, $context),
            $node instanceof TupleNode => $this->parseTuple($node, $input, $context),
            $node instanceof ListNode => $this->parseList($node, $input, $context),
            default => Value::INVALID,
        };
    }

    /**
     * @param RecordNode $node
     * @param mixed $input
     * @param Context $context
     * @return array<string, mixed>|Value
     */
    private function parseRecord(RecordNode $node, mixed $input, Context $context): array|Value
    {
        if (!is_array($input)) {
            return Value::INVALID;
        }

        $record = [];
        foreach ($input as $key => $item) {
            if (!is_string($key)) {
                $context->addIssue(new Issue(
                    'validation.invalid_key_type',
                    [
                        'message' => 'Record keys must be strings, got: ' . gettype($key),
                        'keyValue' => $key,
                    ]
                ));
                return Value::INVALID;
            }
            $context->enterPath($key);
            $result = $this->executeParse($node->type, $item, $context);
            $context->leavePath();

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }

            $record[$key] = $result;
        }
        return $record;
    }

    /**
     * @param ListNode $node
     * @param mixed $input
     * @param Context $context
     * @return list<mixed>|Value
     */
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
            $valueToCheck = $this->extractKeyedValue($node->discriminator, $input);
            if ($valueToCheck instanceof Value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => 'Invalid type for union discriminated type.',
                        'value' => $input,
                        'discriminator' => $node->discriminator,
                    ]
                ));
                return Value::INVALID;
            }

            $discriminatedType = $node->getDiscriminatedType($valueToCheck);
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

        $context->addIssue(new Issue(
            'validation.invalid_union',
            [
                'message' => 'No valid union type found.',
            ]
        ));
        return Value::INVALID;
    }

    /**
     * @param StructNode $node
     * @param mixed $input
     * @param Context $context
     * @return array<string, mixed>|object
     */
    private function parseStruct(StructNode $node, mixed $input, Context $context): array|object
    {
        if (is_array($input) && array_is_list($input)) {
            $context->addIssue(new Issue(
                'validation.invalid_struct',
                [
                    'message' => 'Structs must be of type object, and not empty.',
                    'value' => $input,
                ]
            ));
            return Value::INVALID;
        }

        $struct = [];
        foreach ($node->properties as $propertyNode) {
            if (!$propertyNode->propertyType->isInput()) {
                continue;
            }

            $context->enterPath($propertyNode->name);
            $propertyValue = $this->extractKeyedValue($propertyNode->name, $input);

            if ($propertyValue === Value::INVALID) {
                $context->addIssue(new Issue(
                    'validation.invalid_property',
                    [
                        'message' => "Invalid property: {$propertyNode->name}",
                    ]
                ));
                $context->leavePath();
                return Value::INVALID;
            }

            if ($propertyValue === Value::UNDEFINED) {
                if ($propertyNode->isOptional) {
                    $context->leavePath();
                    continue;
                } else {
                    $context->leavePath();
                    $context->addIssue(new Issue(
                        'validation.missing_property',
                        [
                            'message' => "Missing property: {$propertyNode->name}",
                        ]
                    ));
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

    private function extractKeyedValue(string $key, mixed $input): mixed
    {
        return match (true) {
            is_array($input) => array_key_exists($key, $input) ? $input[$key] : Value::UNDEFINED,
            $input instanceof ArrayAccess => $input->offsetExists($key) ? $input[$key] : Value::UNDEFINED,
            is_object($input) => property_exists($input, $key) ? $input->{$key} : Value::UNDEFINED,
            default => Value::INVALID,
        };
    }
}