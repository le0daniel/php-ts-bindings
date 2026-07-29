<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;

/**
 * Codegen metadata attached to any node: an exported type name and/or a TypeScript brand.
 *
 * The metadata is a pure code generation concern with zero runtime effect: the executor passes
 * straight through, and the ASTOptimizer eliminates the node entirely — cached ASTs are always
 * metadata free, and TypeScript codegen runs on freshly parsed schemas. __toString() and
 * exportPhpCode() delegate to the inner node for the same reason: no export path can leak
 * metadata into a cache, and a metadata-carrying tree stays string-identical to its optimized
 * form.
 */
final readonly class MetadataNode implements NodeInterface, ValidatableNode
{
    public function __construct(
        public NodeInterface $node,
        public ?NamedType    $name = null,
        public ?string       $brand = null,
    )
    {
    }

    public function __toString(): string
    {
        return (string) $this->node;
    }

    public function exportPhpCode(): string
    {
        return $this->node->exportPhpCode();
    }

    public function validate(): void
    {
        if ($this->name === null && $this->brand === null) {
            throw new InvalidArgumentException(
                'MetadataNode without a name or brand is meaningless; use the inner node directly.'
            );
        }

        if ($this->node instanceof MetadataNode) {
            throw new InvalidArgumentException(
                'MetadataNode should not be nested.'
            );
        }
    }
}
