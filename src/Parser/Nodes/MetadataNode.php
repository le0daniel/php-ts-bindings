<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNode;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Override;

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
final readonly class MetadataNode implements NodeInterface, ValidatableNode, WrapsNode
{
    public function __construct(
        public NodeInterface $node,
        public ?NamedType    $name = null,
        public ?string       $brand = null,
    )
    {
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->node;
    }

    #[Override]
    public function exportPhpCode(): string
    {
        return $this->node->exportPhpCode();
    }

    #[Override]
    public function validate(): void
    {
        if ($this->name === null && $this->brand === null) {
            throw new ParserException(
                'MetadataNode without a name or brand is meaningless; use the inner node directly.'
            );
        }

        if ($this->node instanceof MetadataNode) {
            throw new ParserException(
                'MetadataNode should not be nested.'
            );
        }

        $this->assertOneAliasFitsBothDirections();
    }

    /**
     * A single alias has to describe the same type in both directions, because the generated types
     * file declares it exactly once. A castable class whose constructor takes something it does not
     * expose (or exposes something its constructor does not take) has two shapes, and naming both
     * of them the one thing would emit a lying type.
     *
     * Validation is opt-in (AstValidator), so this costs nothing at parse time and surfaces at
     * schema generation, which is the last moment it can.
     */
    private function assertOneAliasFitsBothDirections(): void
    {
        // Two distinct aliases were computed, one per direction — the shapes are free to differ.
        if ($this->name === null || !$this->name->isSameForBothDirections()) {
            return;
        }

        $node = $this->node;

        // NEVER has no input type at all — TypescriptGenerator throws before the alias is reached —
        // so its properties being output only says nothing about the alias.
        if (!$node instanceof CustomCastingNode || $node->strategy === ObjectCastStrategy::NEVER) {
            return;
        }

        // Only a struct has per-direction properties; a cast over a list or a record does not.
        if (!$node->node instanceof StructNode) {
            return;
        }

        $asymmetric = array_find(
            $node->node->properties,
            fn(NodeInterface $property): bool => $property instanceof PropertyNode
                && $property->propertyType !== PropertyType::BOTH,
        );

        if (!$asymmetric instanceof PropertyNode) {
            return;
        }

        $direction = $asymmetric->propertyType === PropertyType::INPUT ? 'input' : 'output';

        throw new ParserException(
            "#[Named] on {$node->fullyQualifiedCastingClass} resolves to one alias \"{$this->name->outputName}\" "
            . "for both directions, but its input and output shapes differ: \"{$asymmetric->name}\" is "
            . "{$direction} only. Every alias is declared once in the generated types file, so one name "
            . "cannot describe both. Compute a name per direction with a closure — "
            . "#[Named(name: Naming::alias(...))], Closure(string \$className, IO \$io): string — or align "
            . "the shapes."
        );
    }
}
