<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Override;
use stdClass;

/**
 * @implements Handler<StructNode>
 */
final readonly class StructHandler implements Handler
{
    /**
     * StructNode::$properties is typed to admit ReferencedNode because the ASTOptimizer builds
     * structs out of interned references on its way to exportPhpCode(). Those structs are only ever
     * exported, never executed: loading the generated file resolves every reference back through the
     * registry, so a struct reaching a handler always holds real PropertyNodes. Asserted rather than
     * branched on because the check is free in production and a failure would be a library bug.
     */
    private const string REFERENCE_INVARIANT = 'A ReferencedNode must be resolved before execution.';

    #[Override]
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): Value|stdClass
    {
        assert($node instanceof StructNode);

        $struct = [];
        foreach ($node->properties as $propertyNode) {
            assert($propertyNode instanceof PropertyNode, self::REFERENCE_INVARIANT);

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
                    IssueMessage::MISSING_PROPERTY,
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

    #[Override]
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        assert($node instanceof StructNode);

        if (!is_array($value) && !$value instanceof stdClass) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => 'Structs must be of type object, and not empty.',
                    'value' => $value,
                ]
            ));
            return Value::INVALID;
        }

        $struct = [];
        foreach ($node->properties as $propertyNode) {
            assert($propertyNode instanceof PropertyNode, self::REFERENCE_INVARIANT);

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
                    IssueMessage::INVALID_TYPE,
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
                        IssueMessage::MISSING_PROPERTY,
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