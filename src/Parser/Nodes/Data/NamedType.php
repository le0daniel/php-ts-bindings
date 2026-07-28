<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

use Le0daniel\PhpTsBindings\Typescript\Data\IO;

/**
 * The resolved #[Named] attribute carried by a MetadataNode: the TypeScript alias to export the
 * node under and the direction(s) the alias applies to.
 */
final readonly class NamedType
{
    public function __construct(
        public string $name,
        public IO     $io = IO::OUTPUT,
    )
    {
    }

    public function appliesTo(IO $io): bool
    {
        return $this->io === IO::BOTH || $this->io === $io;
    }
}
