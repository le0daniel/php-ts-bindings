<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use stdClass;

/**
 * @implements Handler<StructNode>
 */
final class StructHandler implements Handler
{

    /** @param StructNode $node */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): Value|stdClass
    {
        $struct = [];
        foreach ($node->properties as $propertyNode) {
            if (!$propertyNode->propertyType->isOutput()) {
                continue;
            }

            $context->enterPath($propertyNode->name);
            $propertyValue = $this->extractKeyedValue($propertyNode->name, $value);

            if ($propertyValue === Value::INVALID) {
                $context->leavePath();
                return Value::INVALID;
            }

            if ($propertyValue === Value::UNDEFINED) {
                if ($propertyNode->isOptional) {
                    $context->leavePath();
                    continue;
                }

                $context->addIssue(new Issue(
                    'validation.missing_property',
                    [
                        'message' => "Missing property: {$propertyNode->name}",
                    ]
                ));
                $context->leavePath();
                return Value::INVALID;
            }

            $result = $executor->executeSerialize(
                $propertyNode->node,
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

    /** @param StructNode $node */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        if (!is_array($value) && !$value instanceof stdClass) {
            $context->addIssue(new Issue(
                'validation.invalid_struct',
                [
                    'message' => 'Structs must be of type object, and not empty.',
                    'value' => $value,
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
            $propertyValue = match (true) {
                is_array($value) => array_key_exists($propertyNode->name, $value) ? $value[$propertyNode->name] : Value::UNDEFINED,
                default => property_exists($value, $propertyNode->name) ? $value->{$propertyNode->name} : Value::UNDEFINED,
            };

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

            $result = $executor->executeParse(
                $propertyNode->node,
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

    private function extractKeyedValue(string $key, mixed $input): mixed
    {
        if (is_array($input)) {
            return array_key_exists($key, $input) ? $input[$key] : Value::UNDEFINED;
        }

        if ($input instanceof ArrayAccess) {
            return $input->offsetExists($key) ? $input[$key] : Value::UNDEFINED;
        }

        if (!is_object($input)) {
            return Value::INVALID;
        }

        return match (true) {
            property_exists($input, $key) => $input->{$key},
            method_exists($input, '__get') && method_exists($input, '__isset') => $input->__isset($key) ? $input->__get($key) : Value::UNDEFINED,
            default => Value::INVALID,
        };
    }
}