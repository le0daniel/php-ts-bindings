<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use Le0daniel\PhpTsBindings\Contracts\LeafType;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Utils\PHPExport;

final readonly class LiteralType implements NodeInterface, LeafType
{
    public function __construct(
        public string $type,
        public mixed  $value,
    )
    {
    }

    public function __toString(): string
    {
        return match ($this->type) {
            'bool' => $this->value ? 'literal<true>' : 'literal<false>',
            'string' => "literal<'{$this->value}'>",
            default => "literal<{$this->value}>",
        };
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $type = var_export($this->type, true);
        $value = var_export($this->value, true);
        return "new {$className}({$type}, {$value})";
    }

    public function parseValue(mixed $value, $context): mixed
    {
        return $value === $this->value ? $this->value : Value::INVALID;
    }

    public function serializeValue(mixed $value, $context): mixed
    {
        return $value === $this->value ? $this->value : Value::INVALID;
    }
}