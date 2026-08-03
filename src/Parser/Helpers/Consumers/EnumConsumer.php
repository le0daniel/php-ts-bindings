<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\MetadataAttributes;
use Override;
use ReflectionClass;
use UnitEnum;

final readonly class EnumConsumer implements TypeConsumer
{
    #[Override]
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        return enum_exists($state->context->toFullyQualifiedClassName($state->current()->value));
    }

    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);
        $state->advance();

        /** @var class-string<UnitEnum> $fullyQualifiedClassName */
        return MetadataAttributes::wrap(
            new EnumNode($fullyQualifiedClassName),
            new ReflectionClass($fullyQualifiedClassName),
        );
    }
}
