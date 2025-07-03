<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Data\GlobalTypeAliases;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use ReflectionException;

final class AliasConsumer implements TypeConsumer
{
    public function __construct(
        private readonly GlobalTypeAliases $globalTypeAliases,
    )
    {
    }

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $token = $state->current();
        return $state->context->isLocalType($token->value)
            || $state->context->isImportedType($token->value)
            || $state->context->isGeneric($token->value)
            || $this->globalTypeAliases->isGlobalAlias($token->value);
    }

    /**
     * @throws InvalidSyntaxException|ReflectionException
     */
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();

        if ($this->globalTypeAliases->isGlobalAlias($token->value)) {
            $state->advance();
            return $this->globalTypeAliases->getGlobalAlias($token->value);
        }

        if ($state->context->isGeneric($token->value)) {
            $state->advance();
            return $state->context->getGeneric($token->value);
        }

        // Recursive support for locally defined types using @phpstan-type.
        if ($state->context->isLocalType($token->value)) {
            $state->advance();
            return $parser->parse(
                $state->context->getLocalTypeDefinition($token->value),
                $state->context,
            );
        }

        // Recursive support for imported types using @phpstan-import-type.
        if ($state->context->isImportedType($token->value)) {
            $state->advance();

            $importDefinition = $state->context->getImportedTypeInfo($token->value);
            return $parser->parse(
                $importDefinition['typeName'],
                ParsingContext::fromClassString($importDefinition['className']),
            );
        }

        $state->produceSyntaxError("Expected Alias");
    }
}
