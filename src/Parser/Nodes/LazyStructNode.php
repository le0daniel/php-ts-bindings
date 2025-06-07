<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;

final readonly class LazyStructNode implements NodeInterface
{
    public function __construct(
        public string $structName,
        public string $realNodeString,
    )
    {
    }

    public function exportPhpCode(): string
    {
        return "\$registry->get('{$this->structName}')";
    }

    public function __toString(): string
    {
        return $this->realNodeString;
    }
}