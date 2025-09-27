<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\OmitNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PickNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use RuntimeException;
use stdClass;

/**
 * @implements Handler<PickNode|OmitNode>
 */
final class UtilsNodeHandler implements Handler
{

    /**
     * @param PickNode|OmitNode $node
     * @param mixed $value
     * @param Context $context
     * @param Executor $executor
     * @return object
     */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): object
    {
        /** @var array<string, mixed>|object|Value $serializedValue */
        $serializedValue = $executor->executeSerialize($node->node, $value, $context);

        if ($serializedValue === Value::INVALID) {
            return Value::INVALID;
        }

        $valueAsArray = (array) $serializedValue;

        $finalValue = match ($node::class) {
            PickNode::class => array_filter(
                $valueAsArray,
                fn(string $key) => in_array($key, $node->propertyNames, true),
                ARRAY_FILTER_USE_KEY
            ),
            OmitNode::class => array_filter(
                $valueAsArray,
                fn(string $key) => !in_array($key, $node->propertyNames, true),
                ARRAY_FILTER_USE_KEY
            ),
        };

        return (object) $finalValue;
    }

    /**
     * @param PickNode|OmitNode $node
     * @param mixed $value
     * @param Context $context
     * @param Executor $executor
     * @return array|object
     */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): array|object
    {
        $underlyingStructNode = $node->node;

        if (!$underlyingStructNode instanceof StructNode) {
            $className = $node::class;
            throw new RuntimeException("Custom casting is not supported for {$className}");
        }

        $nodeToParse = $underlyingStructNode->filter(match ($node::class) {
            PickNode::class => fn(PropertyNode $property) => in_array($property->name, $node->propertyNames, true),
            OmitNode::class => fn(PropertyNode $property) => !in_array($property->name, $node->propertyNames, true),
            default => throw new RuntimeException("Invalid node type " . $node::class),
        });

        return $executor->executeParse($nodeToParse, $value, $context);
    }
}