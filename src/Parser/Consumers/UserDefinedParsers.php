<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use RuntimeException;

final readonly class UserDefinedParsers implements TypeConsumer
{
    /**
     * @param list<Parser> $parsers
     */
    public function __construct(
        private array $parsers,
    )
    {
    }

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();
        $fqcn = $state->context->toFullyQualifiedClassName($token->value);
        return array_any($this->parsers, fn(Parser $parser) => $parser->canParse($fqcn, $token));
    }

    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $fqcn = $state->context->toFullyQualifiedClassName($token->value);
        $state->advance();

        foreach ($this->parsers as $parser) {
            if ($parser->canParse($fqcn, $token)) {
                return $parser->parse($fqcn, $token);
            }
        }

        throw new RuntimeException("No parser found for {$fqcn}");
    }
}