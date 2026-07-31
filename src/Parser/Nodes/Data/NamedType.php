<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

use Le0daniel\PhpTsBindings\Data\IO;

/**
 * The resolved #[Named] attribute carried by a MetadataNode: the TypeScript alias to export the
 * node under, per direction.
 *
 * Both names are resolved at parse time, so no user closure ever travels in the node tree. Nearly
 * every type resolves to the same alias twice — see same(). Two different names is what an author
 * asks for when the input and output shapes genuinely differ; a single name over differing shapes
 * is rejected by MetadataNode::validate().
 */
final readonly class NamedType
{
    public function __construct(
        public string $inputName,
        public string $outputName,
    )
    {
    }

    /**
     * One alias for both directions, which is the overwhelmingly common case.
     */
    public static function same(string $name): self
    {
        return new self($name, $name);
    }

    public function isSameForBothDirections(): bool
    {
        return $this->inputName === $this->outputName;
    }

    public function nameFor(IO $io): string
    {
        return match ($io) {
            IO::INPUT => $this->inputName,
            IO::OUTPUT => $this->outputName,
        };
    }
}
