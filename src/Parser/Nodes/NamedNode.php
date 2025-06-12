<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class NamedNode implements NodeInterface
{
    public function __construct(
        public NodeInterface $node,
        public string $name,
    )
    {
    }

    public function __toString(): string
    {
        return (string)$this->node;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $name = PHPExport::export($this->name);
        return "new {$className}({$this->node->exportPhpCode()} ,{$name})";
    }
}