<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use DateTimeInterface;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final readonly class DateTimeConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();
        $fqcn = $state->context->toFullyQualifiedClassName($token->value);

        if (is_a($fqcn, DateTimeInterface::class, true)) {
            return true;
        }

        return class_exists($token->value, false) && is_a($token->value, DateTimeInterface::class, true);
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $fqcn = $state->context->toFullyQualifiedClassName($token->value);
        $state->advance();

        /** @var class-string<DateTimeInterface> $className */
        $className = is_a($fqcn, DateTimeInterface::class, true)
            ? $fqcn
            : $token->value;

        return new DateTimeNode($className);
    }
}
