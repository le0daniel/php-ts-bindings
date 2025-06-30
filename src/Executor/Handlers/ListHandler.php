<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;

/**
 * @implements Handler<ListNode>
 */
final class ListHandler implements Handler
{

    /**
     * @param ListNode $node
     * @return Value|array<int, mixed>
     */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        if (!is_iterable($value)) {
            return Value::INVALID;
        }

        $values = [];
        $index = 0;

        foreach ($value as $item) {
            $context->enterPath($index);
            $result = $executor->executeSerialize($node->node, $item, $context);

            if ($result === Value::INVALID) {
                $context->leavePath();
                return Value::INVALID;
            }

            $values[] = $result;

            $index++;
            $context->leavePath();
        }
        return $values;
    }

    /**
     * @param ListNode $node
     * @return Value|array<int, mixed>|object
     */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): array|object
    {
        if (!is_array($value) || !array_is_list($value)) {
            return Value::INVALID;
        }

        if (empty($value)) {
            return $node->asClass ? new $node->asClass([]) : [];
        }

        $list = [];
        $index = 0;

        foreach ($value as $item) {
            $context->enterPath($index);
            $result = $executor->executeParse($node->node, $item, $context);

            if ($result === Value::INVALID) {
                $context->leavePath();
                return Value::INVALID;
            }

            $list[] = $result;
            $context->leavePath();
            $index++;
        }

        return $node->asClass ? new $node->asClass($list) : $list;
    }
}