<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use UnitEnum;

final readonly class EnumConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        return enum_exists($state->context->toFullyQualifiedClassName($state->current()->value));
    }

    public function consume(ParserState $state, TypeParser $parser): EnumNode
    {
        /** @var class-string<UnitEnum> $fqcn */
        $fqcn = $state->context->toFullyQualifiedClassName($state->current()->value);
        $state->advance();
        return new EnumNode($fqcn);
    }
}
