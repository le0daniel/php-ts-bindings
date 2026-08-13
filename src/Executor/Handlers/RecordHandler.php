<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Override;
use stdClass;

/**
 * @implements Handler<RecordNode>
 */
final readonly class RecordHandler implements Handler
{
    /**
     * The cast to stdClass on the last line is the whole point of the type. A PHP array is both of
     * JSON's collections at once, and json_encode picks between them by looking at the keys it
     * finds: `[0 => 'a', 1 => 'b']` encodes as `["a","b"]` and `[]` encodes as `[]`, so a record
     * whose keys happened to run 0..n-1 would reach the client as an array and break a
     * `Record<string, V>` that was correct on every other request. Handing back an object takes
     * that decision away from the data.
     *
     * Keys are not validated here. Serialization never re-checks what the application produced -
     * the same rule SchemaExecutor states for constraints - and PHP guarantees a key is int|string,
     * both of which are a JSON object key.
     */
    #[Override]
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): stdClass|Value
    {
        /** @var RecordNode $node */
        if (! is_array($value) && ! $value instanceof stdClass) {
            $context->addIssue(Issue::invalidType('array', $value));

            return Value::INVALID;
        }

        $value = (array) $value;

        $values = [];
        foreach ($value as $key => $item) {
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
     * @return array<int|string, mixed>|Value::INVALID
     */
    #[Override]
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): array|Value
    {
        /** @var RecordNode $node */

        if (! is_array($value) && ! $value instanceof stdClass) {
            $context->addIssue(Issue::invalidType('array', $value));
            return Value::INVALID;
        }

        // We ensure and cast to an array.
        $value = (array) $value;

        $record = [];
        foreach ($value as $key => $item) {
            $context->enterPath($key);

            $parsedKey = $this->parseKey($node, $key, $context, $executor);
            if ($parsedKey === Value::INVALID) {
                $context->leavePath();
                return Value::INVALID;
            }

            $result = $executor->executeParse($node->node, $item, $context);
            $context->leavePath();

            if ($result === Value::INVALID) {
                return Value::INVALID;
            }

            // PHP folds a numeric string key back into an int here, which is why array<int, V>
            // round trips through a JSON object without anything having to cast it.
            $record[$parsedKey] = $result;
        }

        return $record;
    }

    /**
     * The key is handed to the key node exactly as it arrives, with no coercion of any kind.
     *
     * A JSON object key travels as a string, so it looks like the key node can never see the `int`
     * it declared - but a PHP array is a hash map that folds a canonical decimal integer string
     * into an int the moment it becomes a key, and every route in does that before the handler
     * sees anything. `json_decode($j, true)`, `get_object_vars()` on the object form, and an array
     * built in PHP all agree: `{"42": …}` is already `[42 => …]`, and `{"abc": …}` is still
     * `['abc' => …]`. The coercion is the transport's, and it has already happened.
     *
     * Which means `$key` is exactly what `$record[$key]` on the way out will store, so validating
     * it as it stands is what makes the parsed array match the type that declared it. That is the
     * whole point: `array<string, V>` handed `{"1": …}` fails here rather than quietly returning
     * an `array<int, V>` under a signature promising string keys - PHP has no string key `'1'` to
     * give it.
     *
     * Re-deriving this with filter_var() would be actively wrong, not merely redundant: it reads
     * `' 1'`, `'+1'` and `'-0'` as integers where PHP keeps all three as string keys, so an int
     * keyed record would fold `' 1'` onto the same slot as `'1'`, and a string keyed one would
     * reject a key it can hold perfectly well.
     *
     * @return Value::INVALID|int|string
     */
    private function parseKey(RecordNode $node, int|string $key, Context $context, Executor $executor): Value|int|string
    {
        $parsedKey = $executor->executeParse($node->keyNode, $key, $context);
        if ($parsedKey === Value::INVALID) {
            // Whatever the key node recorded on its way to rejecting the key describes a value at
            // this path, not a key - a union leaves one such issue per arm. They are dropped for
            // the one issue that says which of the two failed. Nothing else has run at this path
            // yet, so there is nothing else to lose.
            $context->removeCurrentIssues();
            $context->addIssue(new Issue(
                IssueMessage::INVALID_KEY_TYPE,
                [
                    'message' => "Record key does not match {$node->keyNode}, got: ".var_export($key, true),
                    'keyValue' => $key,
                ]
            ));

            return Value::INVALID;
        }

        // Anything a key node parses to is an int or a string by construction - RecordKey admits
        // nothing else - so the result is usable as an array key as it stands.
        return $parsedKey;
    }
}
