<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

/**
 * This is used during ast optimization to replace references to other nodes with the actual node.
 * @internal
 */
final readonly class LazyReferencedNode implements NodeInterface
{
    public function __construct(
        public string  $referenceNode,
        private string $originalTypeString,
        private string $registryVariableName,
    )
    {
    }

    public function exportPhpCode(): string
    {
        return "\${$this->registryVariableName}->get('{$this->referenceNode}')";
    }

    public function __toString(): string
    {
        return $this->originalTypeString;
    }
}