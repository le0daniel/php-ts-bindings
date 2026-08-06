<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\IntRange;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\LowercaseString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\NonEmptyString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\NonFalsyString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\NumericString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\UppercaseString;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BoolNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\FloatNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\MixedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\NullNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;

/**
 * Every keyword here that is not a plain PHP type is a PHPStan refinement: the leaf node proves
 * the PHP type, the constraint proves what PHPStan narrowed it to.
 *
 * `int-mask` and `int-mask-of` are deliberately absent. Integer refinement is `int<min, max>`
 * (IntConsumer) plus the four named shorthands below, nothing else.
 */
final readonly class BuiltInLeafConsumer implements TypeConsumer
{
    #[Override]
    public function canConsume(ParserState $state): bool
    {
        if (! $state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        return in_array($state->current()->value, [
            'string',
            'bool',
            'null',
            'float',
            'mixed',
            'truthy-string',
            'non-falsy-string',
            'non-empty-string',
            'numeric-string',
            'lowercase-string',
            'uppercase-string',
            'non-empty-lowercase-string',
            'non-empty-uppercase-string',
            'scalar',
            'positive-int',
            'negative-int',
            'non-negative-int',
            'non-positive-int',
            'numeric',
        ], true);
    }

    /**
     * @throws InvalidSyntaxException
     */
    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $state->advance();

        return match ($token->value) {
            'string' => new StringNode(),
            'bool' => new BoolNode(),
            'null' => new NullNode(),
            'float' => new FloatNode(),
            'mixed' => new MixedNode(),
            'truthy-string',
            'non-falsy-string' => new ConstraintNode(
                new StringNode(),
                [new NonFalsyString()],
            ),
            'non-empty-string' => new ConstraintNode(
                new StringNode(),
                [new NonEmptyString()],
            ),
            'numeric-string' => new ConstraintNode(
                new StringNode(),
                [new NumericString()],
            ),
            'lowercase-string' => new ConstraintNode(
                new StringNode(),
                [new LowercaseString()],
            ),
            'uppercase-string' => new ConstraintNode(
                new StringNode(),
                [new UppercaseString()],
            ),

            // Two refinements over one string. ConstraintNode already carries a list, so the pair
            // needs no nesting; list order is the order the failures are reported in.
            'non-empty-lowercase-string' => new ConstraintNode(
                new StringNode(),
                [new NonEmptyString(), new LowercaseString()],
            ),
            'non-empty-uppercase-string' => new ConstraintNode(
                new StringNode(),
                [new NonEmptyString(), new UppercaseString()],
            ),
            'scalar' => new UnionNode([
                new IntNode(),
                new FloatNode(),
                new BoolNode(),
                new StringNode(),
            ]),
            'positive-int' => new ConstraintNode(
                new IntNode(),
                [new IntRange(min: 1)]
            ),
            'negative-int' => new ConstraintNode(
                new IntNode(),
                [new IntRange(max: -1)]
            ),
            'non-negative-int' => new ConstraintNode(
                new IntNode(),
                [new IntRange(min: 0)]
            ),
            'non-positive-int' => new ConstraintNode(
                new IntNode(),
                [new IntRange(max: 0)]
            ),
            'numeric' => new UnionNode([
                new IntNode(),
                new FloatNode(),
            ]),
            default => $state->produceSyntaxError('Expected valid built-in type, got '.$token->value),
        };
    }
}
