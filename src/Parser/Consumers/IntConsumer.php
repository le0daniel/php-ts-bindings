<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Validators\LengthValidator;

final class IntConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        return $state->currentTokenIs(TokenType::IDENTIFIER, 'int');
    }

    /**
     * @throws InvalidSyntaxException
     */
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $state->advance();

        if (!$state->currentTokenIs(TokenType::LT)) {
            return new BuiltInNode(BuiltInType::INT);
        }

        $state->advance();
        $min = match (true) {
            $state->currentTokenIs(TokenType::INT) => (int)$state->current()->value,
            $state->currentTokenIs(TokenType::IDENTIFIER, 'min') => PHP_INT_MIN,
            default => $state->produceSyntaxError('Expected int or min'),
        };

        $state->advance();
        if (!$state->currentTokenIs(TokenType::COMMA)) {
            $state->produceSyntaxError("Expected comma");
        }
        $state->advance();

        $max = match (true) {
            $state->currentTokenIs(TokenType::INT) => (int)$state->current()->value,
            $state->currentTokenIs(TokenType::IDENTIFIER, 'max') => PHP_INT_MAX,
            default => $state->produceSyntaxError('Expected int or max'),
        };

        $state->advance();
        if (!$state->current()->is(TokenType::GT)) {
            $state->produceSyntaxError("Expected >");
        }

        $state->advance();

        return new ConstraintNode(
            new BuiltInNode(BuiltInType::INT),
            [new LengthValidator(min: $min, max: $max, including: true)]
        );
    }
}