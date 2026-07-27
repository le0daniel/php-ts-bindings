<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Lexer;

/**
 * A byte offset into a source string with the 1-indexed line and column it maps to.
 * Computed lazily, only when an error has to be rendered; tokens carry only the offset.
 */
final readonly class SourceLocation
{
    private function __construct(
        public int $offset,
        public int $line,
        public int $column,
    )
    {
    }

    public static function fromOffset(string $input, int $offset): self
    {
        $before = substr($input, 0, $offset);
        $lastNewline = strrpos($before, "\n");

        return new self(
            $offset,
            substr_count($before, "\n") + 1,
            $lastNewline === false ? $offset + 1 : $offset - $lastNewline,
        );
    }

    /**
     * Renders the offending line plus a caret, so multi line array shapes highlight the
     * line the error is actually on.
     */
    public function highlight(string $input, int $length = 1): string
    {
        $lineStart = $this->offset - ($this->column - 1);
        $lineEnd = strpos($input, "\n", $lineStart);
        $line = $lineEnd === false
            ? substr($input, $lineStart)
            : substr($input, $lineStart, $lineEnd - $lineStart);

        return implode(PHP_EOL, [
            $line,
            str_repeat(' ', $this->column - 1) . str_repeat('^', max(1, $length)),
        ]);
    }
}
