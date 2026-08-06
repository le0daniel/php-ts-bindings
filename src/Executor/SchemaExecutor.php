<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor;

use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\Executor;
use Le0daniel\PhpTsBindings\Executor\Contracts\Handler;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Executor\Handlers\CustomClassHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\IntersectionHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\ListHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\RecordHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\StructHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\TupleHandler;
use Le0daniel\PhpTsBindings\Executor\Handlers\UnionHandler;
use Le0daniel\PhpTsBindings\Parser\Contracts\Coercible;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Override;

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
            coercePrimitives: $options->coercePrimitives,
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
        );

        $result = $this->executeSerialize($node, $output, $context);

        if ($result === Value::INVALID) {
            return new Failure(new Issues($context->issues));
        }

        return new Success($result, new Issues($context->issues));
    }

    /**
     * @internal
     */
    #[Override]
    public function executeSerialize(NodeInterface $node, mixed $data, Context $context): mixed
    {
        // Constraints are never run when serializing, and there is no option to turn them on.
        // Parsing proves refinements about input the application did not produce and cannot
        // trust. Output came out of the application's own code, which PHPStan already analysed
        // against the very return type being serialized here - re-checking it would pay at
        // runtime for a guarantee static analysis has already given.
        if ($node instanceof ConstraintNode) {
            return $this->executeSerialize($node->node, $data, $context);
        }

        // Ordered by how often each case is hit. Leaves outnumber every other node in a typical
        // schema, and MetadataNode is last because the optimizer strips it: in a cached AST that
        // arm can never match.
        $serializedValue = match (true) {
            $node instanceof LeafNode => $node->serializeValue($data, $context),
            array_key_exists($node::class, $this->handlers) => $this->handlers[$node::class]->serialize($node, $data, $context, $this),
            // Codegen metadata has no runtime effect.
            $node instanceof MetadataNode => $this->executeSerialize($node->node, $data, $context),
            // A node class no handler claims is a broken AST, not invalid data. Returning INVALID
            // here would answer with an empty failure; AstValidator throws for the same case.
            default => throw new SchemaException('Unexpected node: '.$node::class),
        };

        // Allow for catching errors at null boundaries during serialization.
        if ($context->partialFailures && $serializedValue === Value::INVALID && $node instanceof UnionNode && $node->acceptsNull()) {
            return null;
        }

        return $serializedValue;
    }

    /**
     * @internal
     */
    #[Override]
    public function executeParse(NodeInterface $node, mixed $data, Context $context): mixed
    {
        if ($node instanceof ConstraintNode) {
            $constrainedValue = $this->executeParse($node->node, $data, $context);
            if ($constrainedValue === Value::INVALID || ! $node->areConstraintsFulfilled($constrainedValue, $context)) {
                return Value::INVALID;
            }

            return $constrainedValue;
        }

        // Ordered by how often each case is hit; see executeSerialize().
        return match (true) {
            $node instanceof LeafNode => $context->coercePrimitives && $node instanceof Coercible
                ? $node->parseValue($node->coerce($data), $context)
                : $node->parseValue($data, $context),
            array_key_exists($node::class, $this->handlers) => $this->handlers[$node::class]->parse($node, $data, $context, $this),
            // Codegen metadata has no runtime effect.
            $node instanceof MetadataNode => $this->executeParse($node->node, $data, $context),
            // See executeSerialize(): an unclaimed node class is a broken AST, not invalid input.
            default => throw new SchemaException('Unexpected node: '.$node::class),
        };
    }
}
