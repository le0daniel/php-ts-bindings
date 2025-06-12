<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

use Stringable;

final readonly class Token implements Stringable
{
    public function __construct(
        public TokenType $type,
        public string    $value,
        public Position  $start,
        public Position  $end,
    )
    {
    }

    public function isAnyTypeOf(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }

    public function is(TokenType $type, ?string $value = null): bool
    {
        if ($this->type !== $type) {
            return false;
        }

        return is_null($value) || $this->value === $value;
    }

    public function coercedValue(): int|bool|float|string
    {
        return match ($this->type) {
            TokenType::INT => (int)$this->value,
            TokenType::FLOAT => (float)$this->value,
            TokenType::BOOL => $this->value === 'true',
            default => $this->value,
        };
    }

    public function __toString(): string
    {
        if ($this->type === TokenType::STRING) {
            return "\"{$this->value}\"";
        }

        return $this->value;
    }
}