<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Tokenizer;

final class LazyStringTokenizer implements \Iterator
{
    private int $currentTokenIndex = 0;
    private array $forwardLookingTokensBuffer = [];

    public function __construct(
        private readonly string $input,
        private readonly array $namespaces,
    )
    {
    }

    public function current(): mixed
    {
        // TODO: Implement current() method.
    }

    public function next(): void
    {
        // TODO: Implement next() method.
    }

    public function key(): mixed
    {
        // TODO: Implement key() method.
    }

    public function valid(): bool
    {
        // TODO: Implement valid() method.
    }

    public function rewind(): void
    {
        // TODO: Implement rewind() method.
    }
}