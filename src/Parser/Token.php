<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

use Stringable;

final readonly class Token implements Stringable
{
    public string $fullyQualifiedValue;

    public function __construct(
        public TokenType $type,
        public string    $value,
        public int       $start,
        public int       $end,
        string|null      $fullyQualifiedValue = null,
    )
    {
        $this->fullyQualifiedValue = $fullyQualifiedValue ?? $this->value;
    }

    /**
     * @param array $namespaces
     * @return Token
     */
    public function setNamespace(array $namespaces): self
    {
        if ($this->type === TokenType::IDENTIFIER) {
            return new self(
                $this->type,
                $this->value,
                $this->start,
                $this->end,
                $namespaces[$this->value] ?? null,
            );
        }

        if ($this->type === TokenType::CLASS_CONST) {
            [$className, $constName] = explode('::', $this->value);

            return new self(
                $this->type,
                $this->value,
                $this->start,
                $this->end,
                isset($namespaces[$className])
                    ? "{$namespaces[$className]}::{$constName}"
                    : null,
            );
        }

        return $this;
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

    public function length(): int
    {
        return $this->end - $this->start;
    }

    public function highlightArea(string $inputString): string
    {
        return implode(PHP_EOL, [
            "Type: {$this->type->name} ({$this->__toString()})",
            $inputString,
            str_pad("", $this->start, ' ') . (
                $this->length() > 0 ? str_pad("", $this->length(), '^') : '|'
            )
        ]);
    }

    public function coercedValue(): int|bool|float|string
    {
        return match ($this->type) {
            TokenType::INT => (int) $this->value,
            TokenType::FLOAT => (float) $this->value,
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