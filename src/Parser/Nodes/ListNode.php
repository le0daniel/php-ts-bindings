<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class ListNode implements NodeInterface, WrapsNode
{
    public function __construct(
        public NodeInterface $node
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return "list<{$this->node}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);

        return "new {$classname}({$this->node->exportPhpCode()})";
    }
}
