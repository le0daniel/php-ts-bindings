<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNodes;
use Le0daniel\PhpTsBindings\Parser\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class IntersectionNode implements NodeInterface, ValidatableNode, WrapsNodes
{
    /**
     * @param list<NodeInterface> $nodes
     */
    public function __construct(
        public array $nodes,
    )
    {
    }

    #[Override]
    public function __toString(): string
    {
        return implode(
            ',',
            $this->nodes
        );
    }

    #[Override]
    public function validate(): void
    {
        if (!Nodes::areAllNodesOfSameStructType($this->nodes)) {
            throw new ParserException("All nodes need to be of the same struct type.");
        }

        if (count($this->nodes) < 2) {
            throw new ParserException("An intersection must be between at least two struct nodes.");
        }
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute($this::class);
        $nodes = PHPExport::exportArray($this->nodes);
        return "new {$className}({$nodes})";
    }
}