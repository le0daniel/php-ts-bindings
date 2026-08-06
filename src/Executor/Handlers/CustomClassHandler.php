<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Override;
use stdClass;
use Throwable;

/**
 * @implements Handler<CustomCastingNode>
 */
final readonly class CustomClassHandler implements Handler
{
    #[Override]
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): stdClass|Value
    {
        assert($node instanceof CustomCastingNode);

        $object = $executor->executeSerialize($node->node, $value, $context);

        if ($object === Value::INVALID) {
            return Value::INVALID;
        }

        if (! $object instanceof stdClass) {
            $objectClass = get_class($object);
            $context->addIssue(
                Issue::internalError(
                    [
                        'message' => "Failed to serialize object($objectClass) to standard class.",
                        'value' => $value,
                        'serializedValue' => $object,
                    ]
                )
            );

            return Value::INVALID;
        }

        return $object;
    }

    #[Override]
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        assert($node instanceof CustomCastingNode);

        if ($node->strategy === ObjectCastStrategy::NEVER) {
            $context->addIssue(Issue::internalError([
                'message' => "{$node->fullyQualifiedCastingClass} cannot be constructed from input.",
                'strategy' => $node->strategy->name,
            ]));

            return Value::INVALID;
        }

        $arrayValue = $executor->executeParse($node->node, $value, $context);
        if ($arrayValue === Value::INVALID) {
            return Value::INVALID;
        }

        if (! is_array($arrayValue)) {
            $context->addIssue(Issue::invalidType('array', $arrayValue));

            return Value::INVALID;
        }

        try {
            if ($node->strategy === ObjectCastStrategy::CONSTRUCTOR) {
                return new ($node->fullyQualifiedCastingClass)(...$arrayValue);
            }

            $instance = new $node->fullyQualifiedCastingClass();
            foreach ($arrayValue as $key => $propertyValue) {
                /** @phpstan-ignore-next-line property.dynamicName */
                $instance->{$key} = $propertyValue;
            }

            return $instance;
        } catch (Throwable $exception) {
            $context->addIssue(Issue::fromThrowable($exception, [
                'message' => "Failed to cast value to {$node->fullyQualifiedCastingClass}: {$exception->getMessage()}",
                'value' => $value,
            ]));

            return Value::INVALID;
        }
    }
}
