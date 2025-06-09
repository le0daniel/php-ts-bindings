<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;

final readonly class CustomCastingNode implements NodeInterface
{
    public function __construct(
        public StructNode|LazyReferencedNode $node,
        public string                        $fullyQualifiedCastingClass,
        public ObjectCastStrategy            $strategy,
    )
    {
    }

    public function __toString(): string
    {
        return $this->fullyQualifiedCastingClass;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $fullyQualifiedCastingClass = PHPExport::absolute($this->fullyQualifiedCastingClass) . '::class';
        $strategy = PHPExport::exportEnumCase($this->strategy);
        return "new {$className}({$this->node->exportPhpCode()}, {$fullyQualifiedCastingClass}, {$strategy})";
    }

    public function cast(array $value): object
    {
        try {
            return new ($this->fullyQualifiedCastingClass)(...$value);
        } catch (Throwable) {
            return Value::INVALID;
        }
    }
}