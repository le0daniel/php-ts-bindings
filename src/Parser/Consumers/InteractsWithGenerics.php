<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

trait InteractsWithGenerics
{

    /**
     * @throws InvalidSyntaxException
     * @return array<int, NodeInterface>
     */
    private function consumeGenerics(ParserState $state, TypeParser $parser, ?int $min = null, ?int $max = null): array
    {
        $isGenericBlock = $state->currentTokenIs(TokenType::LT);
        $generics = [];

        // No Generics
        if (!$isGenericBlock) {
            if (isset($min)) {
                $state->produceSyntaxError("Expected at least {$min} generics, got 0.");
            }
            return [];
        }

        while ($state->canAdvance()) {
            if ($state->currentTokenIs(TokenType::GT)) {
                break;
            }
            $state->advance();
            $generics[] = $parser->consume($state, TokenType::COMMA, TokenType::GT);
        }

        if (!$state->currentTokenIs(TokenType::GT)) {
            $state->produceSyntaxError("Expected '>' to end generics");
        }

        if (empty($generics)) {
            $state->produceSyntaxError("Expected at least one generic type, got none");
        }

        if (isset($min) && count($generics) < $min) {
            $state->produceSyntaxError("Expected at least {$min} generic type(s), got " . count($generics));
        }

        if (isset($max) && count($generics) > $max) {
            $state->produceSyntaxError("Expected at most {$max} generic type(s), got " . count($generics));
        }

        $state->advance();

        return $generics;
    }

}