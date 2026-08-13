<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\ListLength;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParserState;
use Le0daniel\PhpTsBindings\Parser\Helpers\RecordKey;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;

/**
 * Most complex consumer. It consumes the php array type which is a bit of everything:
 * list<int> => ListNode
 * array<int> => RecordNode<string, int>
 * array<int, int> => RecordNode<int, int>
 * array<string, int> => RecordNode<string, int>
 * array{int, int} => TupleNode
 * array{0: int, 1: int} => TupleNode
 *
 * The split between the two collections is by keyword, never by key type. `list` is the only
 * PHPStan type that promises a packed 0..n-1 array, so it is the only one that becomes a JSON
 * array; everything spelled `array<...>` is a record and goes out as a JSON object. `T[]` joins
 * `list` in TypeParser::consumeTypeModifiers() - see the README on why that shorthand is read
 * pragmatically rather than as PHPStan's array<array-key, T>.
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
        if (! $state->currentTokenIs(TokenType::IDENTIFIER)) {
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

        if (! $state->current()->is(TokenType::IDENTIFIER)) {
            $state->produceSyntaxError('Expected Array Type Identifier: array or list');
        }

        // Handle array structures.
        if ($state->current()->value === 'array' && $state->nextTokenIs(TokenType::LBRACE)) {
            // Handles: array{0: string, 1: int} => tuple
            if ($state->peek(2)?->type === TokenType::INT && $state->peek(3)?->is(TokenType::COLON) === true) {
                return $this->consumeIntegerDeterminedTuple($state, $parser);
            }

            // Everything else is the unkeyed spelling: array{string, int}. Keyed shapes never
            // get here because StructConsumer claims them first, and each element is consumed
            // by the full parser, so an element may span any number of tokens:
            // array{DateTimeString<'Y-m-d'>, int|null, array{int, int}}.
            return $this->consumeTuple($state, $parser);
        }

        $maxGenerics = $type === 'list' ? 1 : 2;

        // Consuming of the array type identifier
        $state->advance();

        // No generics. Nothing here says what the elements are, and unlike `array<V>` there is not
        // even a value type to fall back on. Bare `object` and `iterable` already fail here, so
        // this does too rather than emit Record<string, unknown> and pretend.
        if (! $state->currentTokenIs(TokenType::LT)) {
            $state->produceSyntaxError(
                "Bare '{$keyword}' has no single representation. Write list<T>, T[], array<string, T> or array<int, T>."
            );
        }

        $generics = $this->consumeGenerics($state, $parser, min: 1, max: $maxGenerics);

        if ($type === 'list') {
            return $this->applyEmptiness(new ListNode($generics[0]), $isNonEmpty);
        }

        // array<V> is PHPStan's array<array-key, V>. A string key node stands in for array-key:
        // every PHP array key stringifies, so it accepts all of them, and the wire form of a key
        // is a string regardless.
        [$keyNode, $valueNode] = count($generics) === 1
            ? [new StringNode(), $generics[0]]
            : [$generics[0], $generics[1]];

        // Brands and refinements on the key are welcome - the executor validates keys per entry,
        // so `array<non-empty-string, V>` is enforceable rather than silently loosened. What is
        // rejected is a key PHP could not hold in front of `=>` in the first place.
        if (! RecordKey::isUsableAsKey($keyNode)) {
            $state->produceSyntaxError(
                "Array key type must be 'string', 'int' or a union of string/int literals. Got: {$keyNode}"
            );
        }

        return $this->applyEmptiness(new RecordNode($keyNode, $valueNode), $isNonEmpty);
    }

    /**
     * ListLength counts a RecordNode as readily as a ListNode - `non-empty-array<K, V>` is a
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
        if (! $state->currentTokenIs(TokenType::IDENTIFIER, 'array')) {
            $state->produceSyntaxError('Expected array');
        }
        $state->advance();

        if (! $state->currentTokenIs(TokenType::LBRACE)) {
            $state->produceSyntaxError('Expected {');
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
            if (! $state->currentTokenIs(TokenType::INT, (string) count($types))) {
                $state->produceSyntaxError('Expected int with value '.count($types));
            }
            $state->advance();

            if (! $state->currentTokenIs(TokenType::COLON)) {
                $state->produceSyntaxError('Expected colon');
            }
            $state->advance();
            $types[] = $parser->consume($state, TokenType::COMMA, TokenType::RBRACE);
        }

        // Input truncated after a comma leaves the cursor on EOF, which advance() refuses.
        if (! $state->currentTokenIs(TokenType::RBRACE)) {
            $state->produceSyntaxError('Expected }');
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
        if (! $state->currentTokenIs(TokenType::IDENTIFIER, 'array')) {
            $state->produceSyntaxError('Expected array');
        }
        $state->advance();

        if (! $state->currentTokenIs(TokenType::LBRACE)) {
            $state->produceSyntaxError('Expected {');
        }
        $state->advance();

        // array{} and a truncated `array{` both land here with no element to consume.
        if ($state->currentTokenIs(TokenType::RBRACE) || $state->currentTokenIs(TokenType::EOF)) {
            $state->produceSyntaxError('A tuple must declare at least one type.');
        }

        $types = [];
        // The guard above proves the cursor is on a real token, so the body runs at least once.
        do {
            $types[] = $parser->consume($state, TokenType::COMMA, TokenType::RBRACE);

            if ($state->currentTokenIs(TokenType::RBRACE)) {
                break;
            }

            if ($state->currentTokenIs(TokenType::COMMA) && $state->nextTokenIs(TokenType::RBRACE)) {
                $state->advance();
                break;
            }

            if (! $state->currentTokenIs(TokenType::COMMA)) {
                $state->produceSyntaxError('Expected comma for union: array{string, int}');
            }
            $state->advance();
        } while ($state->canAdvance());

        // Input truncated after a comma leaves the cursor on EOF, which advance() refuses.
        if (! $state->currentTokenIs(TokenType::RBRACE)) {
            $state->produceSyntaxError('Expected }');
        }

        $state->advance();

        return new TupleNode($types);
    }
}
