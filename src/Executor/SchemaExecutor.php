<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor;

use ArrayAccess;
use Le0daniel\PhpTsBindings\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\Handlers\CustomClassHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\IntersectionHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\ListHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\RecordHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\StructHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\TupleHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\UnionHandler;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use RuntimeException;
use stdClass;

final readonly class SchemaExecutor implements Executor
{
    /**
     * @var array<class-string<NodeInterface>, Handler<NodeInterface>>
     */
    private array $handlers;

    public function __construct()
    {
        $this->handlers = [
            StructNode::class => new StructHandler(),
            UnionNode::class => new UnionHandler(),
            RecordNode::class => new RecordHandler(),
            CustomCastingNode::class => new CustomClassHandler(),
            IntersectionNode::class => new IntersectionHandler(),
            TupleNode::class => new TupleHandler(),
            ListNode::class => new ListHandler(),
        ];
    }

    public function parse(NodeInterface $node, mixed $input, ParsingOptions $options = new ParsingOptions()): Success|Failure
    {
        $context = new Context(
            partialFailures: $options->partialFailures,
            runConstraints: true,
        );
        $result = $this->executeParse($node, $input, $context);

        if ($result === Value::INVALID) {
            return new Failure(new Issues($context->issues));
        }

        return new Success($result, new Issues($context->issues));
    }

    public function serialize(NodeInterface $node, mixed $output, SerializationOptions $options = new SerializationOptions()): Success|Failure
    {
        $context = new Context(
            partialFailures: $options->partialFailures,
            runConstraints: $options->runConstraints,
        );

        $result = $this->executeSerialize($node, $output, $context);

        if ($result === Value::INVALID) {
            return new Failure(new Issues($context->issues));
        }

        return new Success($result, new Issues($context->issues));
    }

    public function executeSerialize(NodeInterface $node, mixed $data, Context $context): mixed
    {
        // Constraints are ignored when serializing.
        if ($node instanceof ConstraintNode) {
            return $this->executeSerialize($node->node, $data, $context);
        }

        $serializedValue = match (true) {
            array_key_exists($node::class, $this->handlers) => $this->handlers[$node::class]->serialize($node, $data, $context, $this),
            $node instanceof NamedNode => $this->executeSerialize($node->node, $data, $context),
            $node instanceof LeafNode => $node->serializeValue($data, $context),
            default => Value::INVALID,
        };

        // Allow for catching errors at null boundaries during serialization.
        if ($context->partialFailures && $serializedValue === Value::INVALID && $node instanceof UnionNode && $node->acceptsNull) {
            return null;
        }

        return $serializedValue;
    }

    public function executeParse(NodeInterface $node, mixed $data, Context $context): mixed
    {
        if ($node instanceof ConstraintNode) {
            $constrainedValue = $this->executeParse($node->node, $data, $context);
            if ($constrainedValue === Value::INVALID || !$node->areConstraintsFulfilled($constrainedValue, $context)) {
                return Value::INVALID;
            }
            return $constrainedValue;
        }

        return match (true) {
            array_key_exists($node::class, $this->handlers) => $this->handlers[$node::class]->parse($node, $data, $context, $this),
            $node instanceof NamedNode => $this->executeParse($node->node, $data, $context),
            $node instanceof LeafNode => $node->parseValue($data, $context),
            default => Value::INVALID,
        };
    }
}