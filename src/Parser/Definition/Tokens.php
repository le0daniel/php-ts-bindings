<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

use Iterator;
use RuntimeException;

final class Tokens implements Iterator
{
    private int $currentIndex = 0;
    private int $count;

    public function __construct(
        public readonly string $input,
        private readonly array $tokens
    )
    {
        $this->count = count($this->tokens);
    }

    private function getTokenAtIndex(int $index): ?Token
    {
        return $this->tokens[$index] ?? null;
    }

    public function current(): Token
    {
        return $this->getTokenAtIndex($this->currentIndex);
    }

    public function peek(int $offset = 1): ?Token
    {
        return $this->getTokenAtIndex(($this->currentIndex + $offset));
    }

    public function at(int $index): ?Token
    {
        return $this->getTokenAtIndex($index);
    }

    public function currentTokenIs(TokenType $type, ?string $value = null): bool
    {
        if ($this->current()->type !== $type) {
            return false;
        }

        return is_null($value) || $this->current()->value === $value;
    }

    public function currentValueIn(string ... $values): bool
    {
        return in_array($this->current()->value, $values, true);
    }

    public function nextTokenIs(TokenType $type): bool
    {
        return $this->peek()?->type === $type;
    }

    public function canAdvance(int $amount = 1): bool
    {
        return ($this->currentIndex + $amount) < $this->count;
    }

    public function advance(int $amount = 1): void
    {
        if (!$this->canAdvance($amount)) {
            throw new RuntimeException('Cannot advance past end of token');
        }
        $this->currentIndex += $amount;
    }

    public function next(): void
    {
        $this->currentIndex++;
    }

    public function key(): int
    {
        return $this->currentIndex;
    }

    public function valid(): bool
    {
        return $this->currentIndex < $this->count;
    }

    public function rewind(): void
    {
        $this->currentIndex = 0;
    }
}