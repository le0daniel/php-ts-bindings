<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use RuntimeException;

final readonly class BrandedNode implements NodeInterface, ValidatableNode
{
    public function __construct(
        public string        $brand,
        public NodeInterface $node,
    )
    {
    }

    public function exportPhpCode(): string
    {
        throw new RuntimeException("BrandedNode can't be exported to PHP code. Its just a decorator for code generation.");
    }

    public function __toString()
    {
        return (string) $this->node;
    }

    public function validate(): void
    {
        if (!$this->node instanceof BuiltInNode) {
            throw new InvalidArgumentException('BrandedNode can only be used with built-in types');
        }

        if (!in_array($this->node->type, [BuiltInType::INT, BuiltInType::STRING], true)) {
            throw new InvalidArgumentException('BrandedNode can only be used with int or string types');
        }
    }
}