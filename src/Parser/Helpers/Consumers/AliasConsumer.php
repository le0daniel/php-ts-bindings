<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Data\GlobalTypeAliases;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;
use ReflectionException;

final readonly class AliasConsumer implements TypeConsumer
{
    public function __construct(
        private GlobalTypeAliases $globalTypeAliases,
    )
    {
    }

    #[Override]
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
    #[Override]
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
                ParsingScope::fromClassString($importDefinition['className']),
            );
        }

        $state->produceSyntaxError("Expected Alias");
    }
}
