<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use DateTimeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;

final readonly class DateTimeConsumer implements TypeConsumer
{
    #[Override]
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();
        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($token->value);

        // Built in classes are harder to catch as the fully qualified class name might
        // be prefixed with the current namespace.
        if (is_a($fullyQualifiedClassName, DateTimeInterface::class, true)) {
            return true;
        }

        return class_exists($token->value, false) && is_a($token->value, DateTimeInterface::class, true);
    }

    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($token->value);
        $state->advance();

        /** @var class-string<DateTimeInterface> $className */
        $className = is_a($fullyQualifiedClassName, DateTimeInterface::class, true)
            ? $fullyQualifiedClassName
            : $token->value;

        return new DateTimeNode($className);
    }
}
