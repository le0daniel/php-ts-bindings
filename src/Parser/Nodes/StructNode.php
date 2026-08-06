<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Closure;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNodes;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use NoDiscard;
use Override;

final class StructNode implements NodeInterface, ValidatableNode, WrapsNodes
{
    /** @var list<PropertyNode|ReferencedNode> */
    public readonly array $properties;

    /**
     * Properties are exposed as a node.
     */
    public array $nodes {
        get => $this->properties;
    }

    /**
     * Properties are canonically ordered here rather than by a separate pass, so there is only
     * ever one form of a given shape. That keeps a cached AST behaviourally identical to a freshly
     * parsed one, and lets two declarations of the same shape in different orders share a single
     * interned registry entry.
     *
     * @param list<PropertyNode|ReferencedNode> $properties
     */
    public function __construct(
        public readonly StructPhpType $phpType,
        array                         $properties,
    )
    {
        $this->properties = self::canonicalise($properties);
    }


    /**
     * @param list<PropertyNode|ReferencedNode> $properties
     * @return list<PropertyNode|ReferencedNode>
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

    #[Override]
    public function validate(): void
    {
        if (count($this->properties) === 0) {
            throw new ParserException("Cannot create object type with no properties or properties that are not keyed by strings (e.g. ['foo' => 'bar'] is fine, but ['foo'] is not");
        }
    }

    /**
     * @param Closure(PropertyNode): bool $closure
     */
    #[NoDiscard]
    public function filter(Closure $closure): self
    {
        return new self(
            $this->phpType,
            array_filter($this->propertyNodes(), $closure) |> array_values(...),
        );
    }

    /**
     * The properties as everything outside the optimizer sees them. ReferencedNode is admitted by
     * $properties because the ASTOptimizer builds structs out of interned references on its way to
     * exportPhpCode(); those structs are exported, never reshaped or executed.
     *
     * @return list<PropertyNode>
     */
    private function propertyNodes(): array
    {
        $properties = $this->properties;
        assert(
            array_all($properties, static fn($property) => $property instanceof PropertyNode),
            'A struct holding references cannot be reshaped.',
        );

        /** @var list<PropertyNode> $properties */
        return $properties;
    }

    /**
     * @param Closure(PropertyNode): PropertyNode $closure
     */
    #[NoDiscard]
    public function map(Closure $closure): self
    {
        return new self(
            $this->phpType,
            array_map($closure, $this->propertyNodes()),
        );
    }

    #[NoDiscard]
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

    #[Override]
    public function __toString(): string
    {
        $properties = array_map(fn(PropertyNode|ReferencedNode $property) => (string)$property, $this->properties);
        $imploded = implode(', ', $properties);
        return "{$this->phpType->value}{{$imploded}}";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $exportedProperties = PHPExport::exportArray($this->properties);
        $className = PHPExport::absolute(self::class);
        $phpType = PHPExport::exportEnumCase($this->phpType);
        return "new {$className}({$phpType}, {$exportedProperties})";
    }
}