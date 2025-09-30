<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;

/**
 * @implements Handler<UnionNode<NodeInterface>>
 */
final class UnionHandler implements Handler
{

    /** @param UnionNode<NodeInterface> $node */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        // Quick check for nullability.
        if ($value === null && $node->acceptsNull()) {
            return null;
        }

        // Using discriminator for better performance.
        if ($node->isDiscriminated()) {
            $valueToCheck = $this->extractKeyedValue($node->discriminator, $value);
            if ($valueToCheck instanceof Value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => 'Invalid type for union discriminated type.',
                        'value' => $value,
                        'discriminator' => $node->discriminator,
                    ]
                ));
                return Value::INVALID;
            }

            $discriminatedType = $node->getDiscriminatedType($valueToCheck);
            if ($discriminatedType) {
                return $executor->executeSerialize($discriminatedType, $value, $context);
            }

            $context->addIssue(Issue::internalError([
                'message' => 'Failed to find discriminated type for union.',
            ]));

            return Value::INVALID;
        }

        foreach ($node->types as $type) {
            $result = $executor->executeSerialize($type, $value, $context);
            if ($result !== Value::INVALID) {
                $context->removeCurrentIssues();
                return $result;
            }
        }
        return Value::INVALID;
    }

    /** @param UnionNode<NodeInterface> $node */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        if ($value === null && $node->acceptsNull()) {
            return null;
        }

        if ($node->isDiscriminated()) {
            $valueToCheck = $this->extractKeyedValue($node->discriminator, $value);
            if ($valueToCheck instanceof Value) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => 'Invalid type for union discriminated type.',
                        'value' => $value,
                        'discriminator' => $node->discriminator,
                    ]
                ));
                return Value::INVALID;
            }

            $discriminatedType = $node->getDiscriminatedType($valueToCheck);
            if ($discriminatedType) {
                return $executor->executeParse($discriminatedType, $value, $context);
            }
            return Value::INVALID;
        }

        // ToDo Handle probing context.
        foreach ($node->types as $type) {
            $result = $executor->executeParse($type, $value, $context);
            if ($result !== Value::INVALID) {
                $context->removeCurrentIssues();
                return $result;
            }
        }

        $context->addIssue(new Issue(
            IssueMessage::INVALID_TYPE,
            [
                'message' => 'No valid union type found.',
            ]
        ));
        return Value::INVALID;
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