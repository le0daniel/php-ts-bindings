<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum PropertyType
{
    case INPUT;
    case OUTPUT;
    case BOTH;

    public function asString(): string
    {
        return match ($this) {
            self::INPUT => '(input-only)',
            self::OUTPUT => '(output-only)',
            self::BOTH => '',
        };
    }

    public function isInput(): bool
    {
        return match ($this) {
            self::INPUT, self::BOTH => true,
            default => false,
        };
    }

    public function isOutput(): bool {
        return match ($this) {
            self::OUTPUT, self::BOTH => true,
            default => false,
        };
    }
}