<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class RecordNode implements NodeInterface
{
    public function __construct(
        public NodeInterface $type,
    )
    {
    }

    public function __toString(): string
    {
        return "array<string,{$this->type}>";
    }

    public function exportPhpCode(): string
    {
        $classname = PHPExport::absolute(self::class);
        $exportedType = PHPExport::export($this->type);
        return "new {$classname}({$exportedType})";
    }
}