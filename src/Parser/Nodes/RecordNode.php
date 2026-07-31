<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\WrapsNode;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;

final readonly class RecordNode implements NodeInterface, WrapsNode
{
    /**
     * @param NodeInterface $node
     */
    public function __construct(
        public NodeInterface $node,
    )
    {
    }

    #[Override]
    public function __toString(): string
    {
        return "array<string,{$this->node}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $exportedType = PHPExport::export($this->node);
        return "new {$classname}({$exportedType})";
    }
}