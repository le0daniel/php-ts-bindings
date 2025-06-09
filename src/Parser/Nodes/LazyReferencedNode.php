<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

/**
 * @internal
 */
final readonly class LazyReferencedNode implements NodeInterface
{
    public function __construct(
        public string $structName,
        private string $originalTypeString,
    )
    {
    }

    public function exportPhpCode(): string
    {
        return "\$registry->get('{$this->structName}')";
    }

    public function __toString(): string
    {
        return $this->originalTypeString;
    }
}