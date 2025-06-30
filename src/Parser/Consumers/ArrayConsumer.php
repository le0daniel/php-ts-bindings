<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * Most complex consumer. It consumes the php array type which is a bit of everything:
 * array<int> => ListNode
 * array<string, int> => RecordNode
 * array{int, int} => TupleNode
 * array{0: int, 1: int} => TupleNode
 */
final readonly class ArrayConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    /**
     * Add classes which are Collection classes. A collection class is a generic class
     * which is iterable and supports 1 (list) or 2 (list|record) generics. A collection class constructor
     * is expected to accept exactly one argument, a PHP array.
     *
     * Example for it is laravel collections:
     * - Collection<int, array{id: string}> => Array<{id: string}>
     * - Collection<string, array{id: string}> => Record<string>
     * @param array<class-string> $collectionLikeClasses
     */
    public function __construct(
        public array $collectionLikeClasses = [],
    )
    {
    }

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        return in_array($state->current()->value, ['list', 'non-empty-list', 'array', 'non-empty-array'], true)
            || in_array($state->context->toFullyQualifiedClassName($state->current()->value), $this->collectionLikeClasses, true);
    }

    /**
     * @throws InvalidSyntaxException
     */
    public function consume(ParserState $state, TypeParser $parser): RecordNode|ListNode|TupleNode|CustomCastingNode
    {
        $type = match ($state->current()->value) {
            'list', 'non-empty-list' => 'list',
            default => 'array',
        };
        $customType = in_array($state->current()->value, ['list', 'non-empty-list', 'array', 'non-empty-array'], true)
            ? null
            : $state->context->toFullyQualifiedClassName($state->current()->value);

        if (!$state->current()->is(TokenType::IDENTIFIER) || !in_array($type, ['array', 'list'], true)) {
            $state->produceSyntaxError("Expected Array Type Identifier: array or list");
        }

        // Handle array structures.
        if ($state->current()->value === 'array' && $state->nextTokenIs(TokenType::LBRACE)) {
            // Handles: array{0: string, 1: int} => tuple
            if ($state->peek(2)?->type === TokenType::INT && $state->peek(3)?->isAnyTypeOf(TokenType::COLON, TokenType::RBRACE)) {
                return $this->consumeIntegerDeterminedTuple($state, $parser);
            }

            // Handles: array{string,int} => tuple
            if ($state->peek(3)?->isAnyTypeOf(TokenType::COMMA, TokenType::RBRACE)) {
                return $this->consumeTuple($state, $parser);
            }

            $state->produceSyntaxError("Expected array{key: type, ...} or array{key: type, ...} syntax");
        }

        $maxGenerics = $type === 'list' ? 1 : 2;

        // Consuming of the array type identifier
        $state->advance();

        // No generics
        if (!$state->currentTokenIs(TokenType::LT)) {
            return new ListNode(new BuiltInNode(BuiltInType::MIXED));
        }

        $generics = $this->consumeGenerics($state, $parser, min: 1, max: $maxGenerics);

        if (count($generics) === 1) {
            $node = new ListNode($generics[0]);
            return $customType
                ? new CustomCastingNode($node, $customType, ObjectCastStrategy::COLLECTION)
                : $node;
        }

        $keyType = $generics[0];
        if (!$keyType instanceof BuiltInNode) {
            $state->produceSyntaxError("Array key type must be 'string' or 'int'. Got: {$keyType}");
        }

        $node = match ($keyType->type) {
            BuiltInType::STRING => new RecordNode($generics[1]),
            BuiltInType::INT => new ListNode($generics[1]),
            default => $state->produceSyntaxError("Array key type must be 'string' or 'int'. Got: {$keyType}"),
        };

        return $customType
            ? new CustomCastingNode($node, $customType, ObjectCastStrategy::COLLECTION)
            : $node;
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeIntegerDeterminedTuple(ParserState $state, TypeParser $parser): TupleNode
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER, 'array')) {
            $state->produceSyntaxError("Expected array");
        }
        $state->advance();

        if (!$state->currentTokenIs(TokenType::LBRACE)) {
            $state->produceSyntaxError("Expected {");
        }
        $state->advance();

        $types = [];
        while ($state->canAdvance()) {
            if ($state->currentTokenIs(TokenType::RBRACE)) {
                break;
            }

            if ($state->currentTokenIs(TokenType::COMMA)) {
                $state->advance();
                continue;
            }

            if (!$state->currentTokenIs(TokenType::INT, (string)count($types))) {
                $state->produceSyntaxError("Expected int with value " . count($types));
            }
            $state->advance();

            if (!$state->currentTokenIs(TokenType::COLON)) {
                $state->produceSyntaxError("Expected colon");
            }
            $state->advance();
            $types[] = $parser->consume($state, TokenType::COMMA, TokenType::RBRACE);
        }

        $state->advance();
        return new TupleNode($types);
    }

    /**
     * @throws InvalidSyntaxException
     */
    private function consumeTuple(ParserState $state, TypeParser $parser): TupleNode
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER, 'array')) {
            $state->produceSyntaxError("Expected array");
        }
        $state->advance();

        if (!$state->currentTokenIs(TokenType::LBRACE)) {
            $state->produceSyntaxError("Expected {");
        }
        $state->advance();

        $types = [];
        while ($state->canAdvance()) {
            $types[] = $parser->consume($state, TokenType::COMMA, TokenType::RBRACE);

            if ($state->currentTokenIs(TokenType::RBRACE)) {
                break;
            }

            if (!$state->currentTokenIs(TokenType::COMMA)) {
                $state->produceSyntaxError("Expected comma for union: array{string, int}");
            }
            $state->advance();
        }

        $state->advance();
        return new TupleNode($types);
    }
}