<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use DateTimeImmutable;
use DateTimeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class DateTimeConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();

        if ($token->value === 'DateTimeString') {
            return true;
        }

        $fqcn = $state->context->toFullyQualifiedClassName($token->value);

        if (is_a($fqcn, DateTimeInterface::class, true)) {
            return true;
        }

        // Built-in classes like \DateTime may be incorrectly prefixed with the
        // current namespace when the resolver does not know about them.
        return class_exists($token->value, false)
            && is_a($token->value, DateTimeInterface::class, true);
    }

    public function consume(ParserState $state, TypeParser $parser): DateTimeNode
    {
        $token = $state->current();

        if ($token->value === 'DateTimeString') {
            $state->advance();
            $generics = $this->consumeGenerics($state, $parser, null, 1);

            if ($generics === []) {
                return new DateTimeNode(DateTimeImmutable::class);
            }

            $literalNode = $generics[0];
            if (!$literalNode instanceof LiteralNode || $literalNode->type !== LiteralType::STRING) {
                $state->produceSyntaxError("Expected literal string value for DateTimeString format, got: " . $literalNode::class);
            }

            $format = $literalNode->value;
            if (!is_string($format)) {
                $state->produceSyntaxError("Expected literal string value for DateTimeString format, got: " . gettype($format));
            }

            return new DateTimeNode(DateTimeImmutable::class, $format);
        }

        $fqcn = $state->context->toFullyQualifiedClassName($token->value);
        $state->advance();

        /** @var class-string<DateTimeInterface> $className */
        $className = is_a($fqcn, DateTimeInterface::class, true)
            ? $fqcn
            : $token->value;

        return new DateTimeNode($className);
    }
}
