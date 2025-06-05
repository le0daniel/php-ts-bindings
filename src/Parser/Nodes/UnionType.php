<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final class UnionType implements NodeInterface
{
    public function __construct(
        public readonly array $types,
    )
    {
        if (count($this->types) < 2) {
            throw new InvalidArgumentException('Cannot create union type with less than 2 types');
        }
    }

    public function __toString(): string
    {
        return implode('|', array_map(fn(NodeInterface $type) => (string)$type, $this->types));
    }

    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $types = PHPExport::export($this->types);
        return "new {$classname}({$types})";
    }
}