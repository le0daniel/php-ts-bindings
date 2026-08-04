<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Override;

/**
 * @implements Handler<ListNode>
 */
final readonly class ListHandler implements Handler
{

    /**
     * @return Value|array<int, mixed>
     */
    #[Override]
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        assert($node instanceof ListNode);

        if (!is_iterable($value)) {
            $context->addIssue(Issue::invalidType('iterable', $value));
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
     * @return Value|array<int, mixed>
     */
    #[Override]
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): array|Value
    {
        assert($node instanceof ListNode);

        if (!is_array($value) || !array_is_list($value)) {
            $context->addIssue(Issue::invalidType('list', $value));
            return Value::INVALID;
        }

        if (empty($value)) {
            return [];
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

        return $list;
    }
}