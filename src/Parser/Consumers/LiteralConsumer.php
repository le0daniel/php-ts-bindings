<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class LiteralConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        return $state->current()->isAnyTypeOf(TokenType::BOOL, TokenType::STRING, TokenType::FLOAT, TokenType::INT);
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $state->advance();

        return new LiteralNode(
            LiteralType::identifyPrimitiveTypeValue($token->coercedValue()),
            $token->coercedValue(),
        );
    }
}