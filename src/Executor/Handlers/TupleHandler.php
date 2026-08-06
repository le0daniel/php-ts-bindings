<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Override;

/**
 * @implements Handler<TupleNode>
 */
final readonly class TupleHandler implements Handler
{
    /**
     * @return Value|array<int, mixed>
     */
    #[Override]
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): Value|array
    {
        assert($node instanceof TupleNode);

        if (! is_array($value) && ! $value instanceof ArrayAccess) {
            $context->addIssue(Issue::invalidType('array', $value));

            return Value::INVALID;
        }

        $tupleValues = [];
        foreach ($node->nodes as $index => $type) {
            $context->enterPath($index);

            // The parse path proves the arity up front; here the value came from the application
            // and is read one index at a time, so a short tuple has to be caught before indexing
            // past the end of it.
            if (! $this->hasIndex($value, $index)) {
                $context->addIssue(new Issue(
                    IssueMessage::MISSING_PROPERTY,
                    ['message' => "Missing tuple element at index {$index}."],
                ));
                $context->leavePath();

                return Value::INVALID;
            }

            $result = $executor->executeSerialize($type, $value[$index], $context);
            if ($result === Value::INVALID) {
                $context->leavePath();

                return Value::INVALID;
            }
            $tupleValues[] = $result;
            $context->leavePath();
        }

        return $tupleValues;
    }

    /**
     * @return Value|array<int, mixed>
     */
    #[Override]
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): array|Value
    {
        assert($node instanceof TupleNode);

        if (! is_array($value) || ! array_is_list($value)) {
            $context->addIssue(Issue::invalidType('list', $value));

            return Value::INVALID;
        }

        $expectedCount = count($node->nodes);
        if (count($value) !== $expectedCount) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                ['message' => "Expected a tuple of {$expectedCount} elements, got: ".count($value)],
            ));

            return Value::INVALID;
        }

        $tupleValues = [];
        foreach ($node->nodes as $index => $type) {
            $context->enterPath($index);
            $result = $executor->executeParse($type, $value[$index], $context);
            if ($result === Value::INVALID) {
                $context->leavePath();

                return Value::INVALID;
            }
            $tupleValues[] = $result;
            $context->leavePath();
        }

        return $tupleValues;
    }

    /**
     * @param  array<int|string, mixed>|ArrayAccess<int, mixed>  $value
     */
    private function hasIndex(array|ArrayAccess $value, int $index): bool
    {
        return is_array($value)
            ? array_key_exists($index, $value)
            : $value->offsetExists($index);
    }
}
