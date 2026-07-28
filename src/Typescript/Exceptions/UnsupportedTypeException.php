<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Exceptions;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use RuntimeException;

/**
 * Thrown when a schema describes something that has no honest TypeScript representation.
 *
 * Emitting a placeholder instead would push the problem into the generated client, where it shows
 * up as a type error far away from the schema that caused it.
 */
final class UnsupportedTypeException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forNode(NodeInterface $node): self
    {
        return new self(
            "Cannot emit TypeScript for node " . $node::class . ": {$node}"
        );
    }

    public static function uncastableInput(CustomCastingNode $node): self
    {
        return new self(
            "Cannot emit a TypeScript input type for {$node->fullyQualifiedCastingClass}: the class cannot be built from user input. Mark it #[Castable] or keep it out of input schemas."
        );
    }

    public static function emptyEnum(string $enumClassName): self
    {
        return new self(
            "Cannot emit TypeScript for enum {$enumClassName}: it declares no cases."
        );
    }

    public static function conflictingAlias(string $alias, string $existing, string $conflicting): self
    {
        return new self(
            "Type alias {$alias} has conflicting definitions: '{$existing}' and '{$conflicting}'."
            . " If {$alias} comes from #[Named(io: IO::BOTH)], its input and output shapes differ:"
            . " align the shapes or name only one direction."
        );
    }

    public static function reservedAlias(string $alias): self
    {
        return new self(
            "Type alias {$alias} collides with a declaration the generated types file always contains. Pick a different #[Named] or brand name."
        );
    }
}
