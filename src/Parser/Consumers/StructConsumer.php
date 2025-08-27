<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class StructConsumer implements TypeConsumer
{

    public function canConsume(ParserState $state): bool
    {
        if ($state->currentTokenIs(TokenType::IDENTIFIER, 'object')) {
            return true;
        }
        
        return $state->currentTokenIs(TokenType::IDENTIFIER, 'array')
            && $state->peek(1)->is(TokenType::LBRACE)
            && !$state->peek(2)->is(TokenType::INT) // Do not match array{0: string}
            && $state->peek(3)->isAnyTypeOf(TokenType::COLON, TokenType::QUESTION_MARK);
    }

    /**
     * @throws InvalidSyntaxException
     */
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $structType = StructPhpType::from($state->current()->value);
        $state->advance();
        
        
        if (!$state->current()->is(TokenType::LBRACE)) {
            $state->produceSyntaxError("Expected brace");
        }

        $state->advance();
        $properties = [];

        while ($state->canAdvance()) {
            if (!$state->current()->is(TokenType::IDENTIFIER)) {
                $state->produceSyntaxError("Expected identifier");
            }

            $name = $state->current()->value;
            $state->advance();
            $isOptional = $this->consumeOptionalObjectKey($state);

            if (!$state->current()->is(TokenType::COLON)) {
                $state->produceSyntaxError("Expected colon");
            }
            $state->advance();

            $type = $parser->consume($state, TokenType::COMMA, TokenType::RBRACE);
            $properties[] = new PropertyNode($name, $type, $isOptional);
            if ($state->current()->is(TokenType::RBRACE)) {
                break;
            }

            // Accept tailing comma
            if ($state->current()->is(TokenType::COMMA) && $state->nextTokenIs(TokenType::RBRACE)) {
                $state->advance();
                break;
            }

            $state->advance();
        }

        if (!$state->current()->is(TokenType::RBRACE)) {
            $state->produceSyntaxError("Expected brace");
        }

        if (empty($properties)) {
            $state->produceSyntaxError("Expected properties");
        }

        // We move out of the object
        $state->advance();
        return new StructNode($structType, $properties);
    }

    private function consumeOptionalObjectKey(ParserState $state): bool
    {
        if ($state->current()->is(TokenType::QUESTION_MARK)) {
            $state->advance();
            return true;
        }
        return false;
    }
}