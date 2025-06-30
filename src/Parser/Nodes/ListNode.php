<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class ListNode implements NodeInterface
{
    public function __construct(
        public NodeInterface $node,
        public ?string       $asClass = null,
    )
    {
    }

    public function __toString(): string
    {
        $base = "list<{$this->node}>";
        return $this->asClass ? "{$base}@{$this->asClass}" : $base;
    }

    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $class = $this->asClass ? PHPExport::absolute($this->asClass) . '::class' : 'null';
        return "new {$classname}({$this->node->exportPhpCode()}, {$class})";
    }
}