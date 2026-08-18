<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use DateTimeImmutable;
use DateTimeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;

final readonly class DateTimeConsumer implements TypeConsumer
{
    #[Override]
    public function canConsume(ParserState $state): bool
    {
        if (! $state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();

        return is_a($state->context->toFullyQualifiedClassName($token->value), DateTimeInterface::class, true);
    }

    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        /** @var class-string<DateTimeInterface> $className */
        $className = $state->context->toFullyQualifiedClassName($state->current()->value);
        $state->advance();

        // The interface itself is a valid type to declare but nothing can be hydrated from it:
        // DateTimeInterface::createFromInterface() does not exist. It stands for the immutable
        // implementation, which is what the PHPStan extension resolves it to as well. Every
        // concrete class is kept as written, so `DateTime` stays mutable.
        return new DateTimeNode(
            $className === DateTimeInterface::class ? DateTimeImmutable::class : $className,
        );
    }
}
