<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class PropertyNode implements NodeInterface
{
    public function __construct(
        public string        $name,
        public NodeInterface $node,
        public bool          $isOptional,
        public PropertyType  $propertyType = PropertyType::BOTH,
    ) {}

    public function changePropertyType(PropertyType $propertyType): self
    {
        return new self($this->name, $this->node, $this->isOptional, $propertyType);
    }

    public function __toString(): string
    {
        $optional = $this->isOptional ? '?' : '';
        return "{$this->name}{$optional}: {$this->node}{$this->propertyType->asString()}";
    }

    public function changeType(PropertyType $type): self
    {
        return new self($this->name, $this->node, $this->isOptional, $type);
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = $this->node->exportPhpCode();
        $isOptional = PHPExport::export($this->isOptional);
        $name = PHPExport::export($this->name);
        $propertyType = $this->propertyType === PropertyType::BOTH
            ? ''
            : ',' . PHPExport::exportEnumCase($this->propertyType);

        return "new {$className}({$name}, {$type}, {$isOptional}{$propertyType})";
    }
}