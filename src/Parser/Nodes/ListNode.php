<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use SensitiveParameter;

final readonly class ListNode implements NodeInterface
{
    public function __construct(
        public NodeInterface $node
    )
    {
    }

    public function __toString(): string
    {
        return "list<{$this->node}>";
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = $this->node->exportPhpCode();
        return "new {$className}({$type})";
    }
}