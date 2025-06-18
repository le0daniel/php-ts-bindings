<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use stdClass;

/**
 * @implements Handler<CustomCastingNode>
 */
final class CustomClassHandler implements Handler
{


    /** @param CustomCastingNode $node */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): stdClass|Value
    {
        $object = $executor->executeSerialize($node->node, $value, $context);

        if ($object === Value::INVALID) {
            return Value::INVALID;
        }

        if (!$object instanceof stdClass) {
            $objectClass = get_class($object);
            $context->addIssue(new Issue(
                'validation.invalid_cast',
                [
                    "message" => "Failed to serialize object($objectClass) to standard class.",
                    "value" => $value,
                    "serializedValue" => $object,
                ]
            ));
            return Value::INVALID;
        }

        return $object;
    }

    /** @param CustomCastingNode $node */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        $arrayValue = $executor->executeParse($node->node, $value, $context);
        if ($arrayValue === Value::INVALID || !is_array($arrayValue)) {
            return Value::INVALID;
        }
        return $node->cast($arrayValue, $context);
    }
}