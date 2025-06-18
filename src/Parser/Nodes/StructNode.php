<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class StructNode implements NodeInterface, ValidatableNode
{
    /**
     * @param non-empty-list<PropertyNode|ReferencedNode> $properties
     */
    public function __construct(
        public StructPhpType $phpType,
        public array         $properties,
    )
    {
    }

    public function validate(): void
    {
        if (empty($this->properties)) {
            throw new InvalidArgumentException("Cannot create object type with no properties or properties that are not keyed by strings (e.g. ['foo' => 'bar'] is fine, but ['foo'] is not");
        }
    }

    /**
     * @return PropertyNode[]
     */
    public function sortedProperties(): array
    {
        $properties = $this->properties;

        // Sort by name, then by type
        usort($properties, function (PropertyNode $a, PropertyNode $b): int {
            $nameComparison = strcmp($a->name, $b->name);
            if ($nameComparison !== 0) {
                return $nameComparison;
            }
            return $a->propertyType->name <=> $b->propertyType->name;
        });

        return $properties;
    }

    public function getProperty(string $name): ?PropertyNode
    {
        return array_find($this->properties, fn(PropertyNode $property) => $property->name === $name);
    }

    public function __toString(): string
    {
        $properties = array_map(fn(PropertyNode|ReferencedNode $property) => (string) $property, $this->properties);
        $imploded = implode(', ', $properties);
        return "{$this->phpType->value}{{$imploded}}";
    }

    public function exportPhpCode(): string
    {
        $exportedProperties = PHPExport::exportArray($this->properties);
        $className = PHPExport::absolute(self::class);
        $phpType = PHPExport::exportEnumCase($this->phpType);
        return "new {$className}({$phpType}, {$exportedProperties})";
    }
}