<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use UnitEnum;

final readonly class EnumCasesConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();
        $fqcn = $state->context->toFullyQualifiedClassName($token->value);

        return enum_exists($fqcn);
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $fqcn = $state->context->toFullyQualifiedClassName($token->value);
        $state->advance();

        /** @var class-string<UnitEnum> $fqcn */
        return new EnumNode($fqcn);
    }
}
