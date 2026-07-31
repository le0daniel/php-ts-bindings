<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
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

        if (!is_array($value) && !$value instanceof ArrayAccess) {
            return Value::INVALID;
        }

        $tupleValues = [];
        foreach ($node->nodes as $index => $type) {
            $context->enterPath($index);
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

        if (!is_array($value) || !array_is_list($value)) {
            return Value::INVALID;
        }

        $expectedCount = count($node->nodes);
        if (count($value) !== $expectedCount) {
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
}