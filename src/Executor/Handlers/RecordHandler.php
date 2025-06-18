<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use stdClass;

/**
 * @implements Handler<RecordNode>
 */
final class RecordHandler implements Handler
{

    /** @param RecordNode $node */
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): stdClass|Value
    {
        if (!is_iterable($value)) {
            return Value::INVALID;
        }

        $values = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                $context->addIssue(new Issue(
                    'validation.invalid_key_type',
                    [
                        'message' => 'Record keys must be strings, got: ' . gettype($key),
                        'keyValue' => $key,
                    ]
                ));
                return Value::INVALID;
            }

            $context->enterPath($key);
            $result = $executor->executeSerialize($node->node, $item, $context);
            $context->leavePath();

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }
            $values[$key] = $result;
        }
        return (object) $values;
    }

    /**
     * @param RecordNode $node
     * @return array<string, mixed>|Value
     */
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): array|Value
    {
        if (!is_array($value)) {
            return Value::INVALID;
        }

        $record = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                $context->addIssue(new Issue(
                    'validation.invalid_key_type',
                    [
                        'message' => 'Record keys must be strings, got: ' . gettype($key),
                        'keyValue' => $key,
                    ]
                ));
                return Value::INVALID;
            }
            $context->enterPath($key);
            $result = $executor->executeParse($node->node, $item, $context);
            $context->leavePath();

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }

            $record[$key] = $result;
        }
        return $record;
    }
}