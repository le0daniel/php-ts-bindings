<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final class PickNode implements NodeInterface
{
    public function __construct(
        public StructNode|CustomCastingNode|ReferencedNode $node,
        public array                                       $propertyNames,
    )
    {
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $pickedProperties = PHPExport::export($this->propertyNames);

        return "new {$className}({$this->node->exportPhpCode()}, {$pickedProperties})";
    }

    public function __toString()
    {
        $properties = implode('|', $this->propertyNames);
        return "Pick<{$this->node}, {$properties}>";
    }
}