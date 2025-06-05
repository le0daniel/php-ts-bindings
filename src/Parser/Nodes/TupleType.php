<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class TupleType implements NodeInterface
{
    /**
     * @param list<NodeInterface> $types
     */
    public function __construct(public array $types)
    {
        if (empty($this->types) || !array_is_list($this->types)) {
            throw new InvalidArgumentException("Expected non empty list of types.");
        }
    }

    public function __toString(): string
    {
        $typeString = Arrays::mapWithKeys($this->types, fn(int $key, NodeInterface $type) => "{$key}: {$type}");
        $imploded = implode(', ', $typeString);
        return "array{$imploded}";
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $types = array_map(fn(NodeInterface $type) => $type->exportPhpCode(), $this->types);
        $imploded = implode(', ', $types);
        return "new {$className}([{$imploded}])";
    }
}