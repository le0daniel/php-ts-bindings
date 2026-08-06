<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNodes;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class TupleNode implements NodeInterface, ValidatableNode, WrapsNodes
{
    /**
     * @param  non-empty-list<NodeInterface>  $nodes
     */
    public function __construct(public array $nodes)
    {
    }

    #[Override]
    public function __toString(): string
    {
        $typeString = Arrays::mapWithKeys($this->nodes, fn (int $key, NodeInterface $type) => "{$key}: {$type}");
        $imploded = implode(', ', $typeString);

        return 'array{'.$imploded.'}';
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $types = array_map(fn (NodeInterface $type) => $type->exportPhpCode(), $this->nodes);
        $imploded = implode(', ', $types);

        return "new {$className}([{$imploded}])";
    }

    #[Override]
    public function validate(): void
    {
    }
}
