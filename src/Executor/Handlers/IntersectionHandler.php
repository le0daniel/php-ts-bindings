<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Handlers;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Override;
use stdClass;

/**
 * @implements Handler<IntersectionNode>
 */
final readonly class IntersectionHandler implements Handler
{
    #[Override]
    public function serialize(NodeInterface $node, mixed $value, Context $context, Executor $executor): stdClass|Value
    {
        assert($node instanceof IntersectionNode);

        /** @var array<string, mixed> $intersectionValues */
        $intersectionValues = [];

        foreach ($node->nodes as $type) {
            $partialObject = $executor->executeSerialize($type, $value, $context);
            if ($partialObject === Value::INVALID) {
                return Value::INVALID;
            }

            $intersectionValues[] = (array) $partialObject;
        }

        return (object) array_merge(...$intersectionValues);
    }

    #[Override]
    public function parse(NodeInterface $node, mixed $value, Context $context, Executor $executor): mixed
    {
        assert($node instanceof IntersectionNode);

        /** @var array<string, mixed> $intersectionValues */
        $intersectionValues = [];

        /** @var 'array'|'object'|null $mode */
        $mode = null;

        foreach ($node->nodes as $type) {
            $partialObject = $executor->executeParse($type, $value, $context);
            if ($partialObject === Value::INVALID) {
                return Value::INVALID;
            }

            $mode ??= is_array($partialObject) ? 'array' : 'object';
            if (
                ($mode === 'object' && !$partialObject instanceof stdClass) ||
                ($mode === 'array' && !is_array($partialObject))
            ) {
                $context->addIssue(new Issue(
                    IssueMessage::INVALID_TYPE,
                    [
                        'message' => "intersection expects value to be of same struct type: object or array<string, mixed>",
                        'expected' => $mode,
                        'got' => $partialObject,
                    ],
                ));
                return Value::INVALID;
            }

            $intersectionValues[] = (array) $partialObject;
        }

        return match ($mode) {
            'array' => array_merge(...$intersectionValues),
            'object' => (object) array_merge(...$intersectionValues),
            default => throw new SchemaException("Invalid mode {$mode}"),
        };
    }
}