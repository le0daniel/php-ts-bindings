<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class StructNode implements NodeInterface
{
    /**
     * @param array<string, NodeInterface> $properties
     */
    public function __construct(
        public StructPhpType $phpType,
        public array         $properties,
    )
    {
        if (empty($this->properties) || array_is_list($this->properties)) {
            throw new InvalidArgumentException("Cannot create object type with no properties or properties that are not keyed by strings (e.g. ['foo' => 'bar'] is fine, but ['foo'] is not");
        }
    }

    public function __toString(): string
    {
        $props = [];
        foreach ($this->properties as $key => $type) {
            $props[] = "{$key}:{$type}";
        }

        return "{$this->phpType->value}{" . implode(', ', $props) . '}';
    }

    public function exportPhpCode(): string
    {
        $flattenProperties = [];
        foreach ($this->properties as $name => $property) {
            $name = PHPExport::export($name);
            $property = PHPExport::export($property);
            $flattenProperties[] = "{$name} => {$property}";
        }

        $exportedProperties = implode(', ', $flattenProperties);
        $className = PHPExport::absolute(self::class);
        $phpType = PHPExport::exportEnumCase($this->phpType);

        return "new {$className}({$phpType}, [{$exportedProperties}])";
    }
}