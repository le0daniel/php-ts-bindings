<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class IntersectionNode implements NodeInterface, ValidatableNode
{
    /**
     * @param list<NodeInterface> $types
     */
    public function __construct(
        public array $types,
    )
    {
    }

    public function __toString(): string
    {
        return implode(
            '&',
            $this->types
        );
    }

    public function validate(): void
    {
        if (!Nodes::areAllNodesOfSameStructType($this->types)) {
            throw new InvalidArgumentException("All nodes need to be of the same struct type.");
        }

        if (count($this->types) < 2) {
            throw new InvalidArgumentException("An intersection must be between at least two struct nodes.");
        }
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute($this::class);
        $nodes = PHPExport::exportArray($this->types);
        return "new {$className}({$nodes})";
    }
}