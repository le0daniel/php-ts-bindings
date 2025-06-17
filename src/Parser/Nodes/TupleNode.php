<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValidatableNode;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class TupleNode implements NodeInterface, ValidatableNode
{
    /**
     * @param non-empty-list<NodeInterface> $types
     */
    public function __construct(public array $types)
    {
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

    public function validate(): void
    {
        if (empty($this->types)) {
            throw new InvalidArgumentException("TupleNode must have at least one type");
        }
    }
}