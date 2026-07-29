<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Closure;
use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class StructNode implements NodeInterface, ValidatableNode
{
    /** @var non-empty-list<PropertyNode|ReferencedNode> */
    public array $properties;

    /**
     * Properties are canonically ordered here rather than by a separate pass, so there is only
     * ever one form of a given shape. That keeps a cached AST behaviourally identical to a freshly
     * parsed one, and lets two declarations of the same shape in different orders share a single
     * interned registry entry.
     *
     * @param non-empty-list<PropertyNode|ReferencedNode> $properties
     */
    public function __construct(
        public StructPhpType $phpType,
        array                $properties,
    )
    {
        $this->properties = self::canonicalise($properties);
    }


    /**
     * @param non-empty-list<PropertyNode|ReferencedNode> $properties
     * @return non-empty-list<PropertyNode|ReferencedNode>
     */
    private static function canonicalise(array $properties): array
    {
        // ReferencedNode carries no name to sort by. The ASTOptimizer rebuilds structs by mapping
        // over an already canonical list, so order is preserved and sorting is unnecessary there.
        if (!array_all($properties, static fn(PropertyNode|ReferencedNode $property) => $property instanceof PropertyNode)) {
            return $properties;
        }

        /** @var non-empty-list<PropertyNode> $properties */
        usort($properties, static function (PropertyNode $a, PropertyNode $b): int {
            $byName = strcmp($a->name, $b->name);
            return $byName !== 0
                ? $byName
                : $a->propertyType->name <=> $b->propertyType->name;
        });

        return $properties;
    }

    public function validate(): void
    {
        if (empty($this->properties)) {
            throw new InvalidArgumentException("Cannot create object type with no properties or properties that are not keyed by strings (e.g. ['foo' => 'bar'] is fine, but ['foo'] is not");
        }
    }

    /**
     * @param Closure(PropertyNode): bool $closure
     * @return self
     */
    public function filter(Closure $closure): self
    {
        return new self(
            $this->phpType,
            array_values(array_filter($this->properties, $closure))
        );
    }

    /**
     * @param Closure(PropertyNode): PropertyNode $closure
     * @return self
     */
    public function map(Closure $closure): self
    {
        return new self(
            $this->phpType,
            array_map($closure, $this->properties),
        );
    }

    public function ofType(StructPhpType $type): self
    {
        return new self($type, $this->properties);
    }

    public function getProperty(string $name): ?PropertyNode
    {
        /** @var list<PropertyNode> $properties */
        $properties = $this->properties;
        return array_find($properties, fn(PropertyNode $property) => $property->name === $name);
    }

    public function hasProperty(string $name): bool
    {
        return $this->getProperty($name) !== null;
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