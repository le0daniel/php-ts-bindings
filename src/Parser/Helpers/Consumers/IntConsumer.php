<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\IntRange;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\Utils\Lexemes;
use Override;

final readonly class IntConsumer implements TypeConsumer
{
    #[Override]
    public function canConsume(ParserState $state): bool
    {
        return $state->currentTokenIs(TokenType::IDENTIFIER, 'int');
    }

    /**
     * @throws InvalidSyntaxException
     */
    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $state->advance();

        if (! $state->currentTokenIs(TokenType::LT)) {
            return new IntNode();
        }

        // `min` and `max` become null, not PHP_INT_MIN/PHP_INT_MAX: `int<min, 100>` says there is
        // no lower bound, which is a different claim from "the bound is this platform's smallest
        // int". Both validate identically; null keeps the exported cache and the diagnostic label
        // readable.
        $state->advance();
        $min = match (true) {
            $state->currentTokenIs(TokenType::INT) => Lexemes::decodeInt($state->current()->value),
            $state->currentTokenIs(TokenType::IDENTIFIER, 'min') => null,
            default => $state->produceSyntaxError('Expected int or min'),
        };

        $state->advance();
        if (! $state->currentTokenIs(TokenType::COMMA)) {
            $state->produceSyntaxError('Expected comma');
        }
        $state->advance();

        $max = match (true) {
            $state->currentTokenIs(TokenType::INT) => Lexemes::decodeInt($state->current()->value),
            $state->currentTokenIs(TokenType::IDENTIFIER, 'max') => null,
            default => $state->produceSyntaxError('Expected int or max'),
        };

        $state->advance();
        if (! $state->current()->is(TokenType::GT)) {
            $state->produceSyntaxError('Expected >');
        }

        $state->advance();

        return new ConstraintNode(
            new IntNode(),
            [new IntRange($min, $max)]
        );
    }
}
