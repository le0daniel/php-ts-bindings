<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Lexer\SourceLocation;
use Le0daniel\PhpTsBindings\Parser\Lexer\Token;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Throwable;

/**
 * A cursor over the token stream produced by the Lexer.
 *
 * The Lexer emits a lossless stream, whitespace included. This is the boundary where trivia
 * stops mattering: whitespace is dropped once, here, so that every lookahead below counts
 * meaningful tokens only. Tokens keep their absolute byte offsets into $input, so dropping
 * whitespace does not disturb error rendering.
 *
 * Deliberately not an Iterator: consumers drive the cursor explicitly through advance(), peek() and
 * currentTokenIs(). An Iterator would have offered a second way to move that does not enforce
 * canAdvance(), so a foreach could walk off the end of a stream the parser considers exhausted.
 */
final class ParserState
{
    private int $currentIndex = 0;
    private readonly int $count;

    /** @var non-empty-list<Token> */
    private readonly array $tokens;

    /**
     * @param string $input
     * @param non-empty-list<Token> $tokens The raw, lossless token stream.
     * @param ParsingScope $context
     */
    public function __construct(
        public readonly string       $input,
        array                        $tokens,
        public readonly ParsingScope $context,
    )
    {
        $significant = array_values(
            array_filter($tokens, static fn(Token $token): bool => $token->type !== TokenType::WHITESPACE)
        );

        // The Lexer always terminates the stream with EOF, which is never whitespace.
        if ($significant === []) {
            throw new ParserException('The token stream must contain at least one significant token.');
        }

        $this->tokens = $significant;
        $this->count = count($significant);
    }

    private function getTokenAtIndex(int $index): ?Token
    {
        return $this->tokens[$index] ?? null;
    }

    /**
     * The cursor never moves past EOF, so this always resolves. The final token is returned
     * as a fallback rather than null so that the declared return type is honest.
     */
    public function current(): Token
    {
        return $this->getTokenAtIndex($this->currentIndex) ?? $this->tokens[$this->count - 1];
    }

    public function peek(int $offset = 1): ?Token
    {
        return $this->getTokenAtIndex($this->currentIndex + $offset);
    }

    public function at(int $index): ?Token
    {
        return $this->getTokenAtIndex($index);
    }

    public function currentTokenIs(TokenType $type, ?string $value = null): bool
    {
        return $this->current()->is($type, $value);
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
            throw new ParserException('Cannot advance past end of token');
        }
        $this->currentIndex += $amount;
    }

    public function highlightCurrentToken(): string
    {
        $token = $this->current();
        $location = SourceLocation::fromOffset($this->input, $token->offset);

        return implode(PHP_EOL, [
            "Type: {$token->type->name} ({$token->value})",
            $location->highlight($this->input, strlen($token->value)),
        ]);
    }

    public function produceSyntaxError(string $message, ?Throwable $throwable = null): never
    {
        throw new InvalidSyntaxException(
            implode(PHP_EOL, [
                "Syntax Error: {$message}",
                $this->highlightCurrentToken(),
            ]),
            previous: $throwable,
        );
    }
}
