<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\Lexemes;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;

final readonly class LiteralConsumer implements TypeConsumer
{
    private const array BOOLEANS = ['true', 'false'];

    #[Override]
    public function canConsume(ParserState $state): bool
    {
        $token = $state->current();

        // The lexer no longer decides that `true` is a boolean, so a boolean literal
        // arrives as a plain identifier. This consumer runs first, ahead of
        // BuiltInLeafConsumer, which owns `null`.
        if ($token->is(TokenType::IDENTIFIER)) {
            return in_array($token->value, self::BOOLEANS, true);
        }

        return $token->isAnyTypeOf(TokenType::STRING, TokenType::FLOAT, TokenType::INT);
    }

    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $state->advance();

        $value = match ($token->type) {
            TokenType::STRING => Lexemes::decodeString($token->value),
            TokenType::INT => Lexemes::decodeInt($token->value),
            TokenType::FLOAT => Lexemes::decodeFloat($token->value),
            default => $token->value === 'true',
        };

        return new LiteralNode(
            LiteralType::identifyPrimitiveTypeValue($value),
            $value,
        );
    }
}