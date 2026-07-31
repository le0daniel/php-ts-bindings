<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Constraints\ListLength;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\MixedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Utils\Nodes;
use Override;

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

    public function __construct()
    {
    }

    #[Override]
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        return in_array($state->current()->value, ['list', 'non-empty-list', 'array', 'non-empty-array'], true);
    }

    /**
     * `non-empty-list` and `non-empty-array` are the same shape as their plain counterparts plus
     * a minimum element count, so the keyword is split into the shape it describes and the
     * refinement it adds rather than being normalised away.
     *
     * @throws InvalidSyntaxException
     */
    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $keyword = $state->current()->value;
        $type = match ($keyword) {
            'list', 'non-empty-list' => 'list',
            default => 'array',
        };
        $isNonEmpty = $keyword === 'non-empty-list' || $keyword === 'non-empty-array';

        if (!$state->current()->is(TokenType::IDENTIFIER)) {
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
            return $this->applyEmptiness(new ListNode(new MixedNode()), $isNonEmpty);
        }

        $generics = $this->consumeGenerics($state, $parser, min: 1, max: $maxGenerics);

        if (count($generics) === 1) {
            return $this->applyEmptiness(new ListNode($generics[0]), $isNonEmpty);
        }

        // A branded key (array<BrandedString<'k'>, V>) is still a string key on the wire.
        // Constraints are deliberately NOT unwrapped: a constrained key (array<non-empty-string, V>)
        // could never be validated at runtime, so it is rejected instead of silently loosened.
        $keyType = Nodes::unwrapMetadata($generics[0]);
        $node = match (true) {
            $keyType instanceof StringNode => new RecordNode($generics[1]),
            $keyType instanceof IntNode => new ListNode($generics[1]),
            default => $state->produceSyntaxError("Array key type must be 'string' or 'int'. Got: {$keyType}"),
        };

        return $this->applyEmptiness($node, $isNonEmpty);
    }

    /**
     * ListLength counts a RecordNode as readily as a ListNode - `non-empty-array<string, V>` is a
     * record, and both are a plain PHP array by the time the executor sees them.
     */
    private function applyEmptiness(RecordNode|ListNode $node, bool $isNonEmpty): NodeInterface
    {
        return $isNonEmpty
            ? new ConstraintNode($node, [new ListLength(min: 1)])
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

            if ($state->currentTokenIs(TokenType::COMMA) && $state->nextTokenIs(TokenType::RBRACE)) {
                $state->advance();
                break;
            }

            if ($state->currentTokenIs(TokenType::COMMA)) {
                $state->advance();
                continue;
            }

            // Compares the raw lexeme, so exotic spellings such as array{+0: string}
            // are intentionally not accepted here.
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
        if ($types === []) {
            $state->produceSyntaxError('A tuple must declare at least one type.');
        }

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

            if ($state->currentTokenIs(TokenType::COMMA) && $state->nextTokenIs(TokenType::RBRACE)) {
                $state->advance();
                break;
            }

            if (!$state->currentTokenIs(TokenType::COMMA)) {
                $state->produceSyntaxError("Expected comma for union: array{string, int}");
            }
            $state->advance();
        }

        $state->advance();
        if ($types === []) {
            $state->produceSyntaxError('A tuple must declare at least one type.');
        }

        return new TupleNode($types);
    }
}