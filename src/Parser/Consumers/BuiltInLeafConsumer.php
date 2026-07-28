<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BoolNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\FloatNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\MixedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\NullNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Validators\LengthValidator;
use Le0daniel\PhpTsBindings\Validators\NonEmptyString;
use Le0daniel\PhpTsBindings\Validators\NonFalsyStringValidator;

final class BuiltInLeafConsumer implements TypeConsumer
{

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
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
            'scalar',
            'positive-int',
            'negative-int',
            "non-negative-int",
            'non-positive-int',
            'numeric',
        ]);
    }

    /**
     * @throws InvalidSyntaxException
     */
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
                [new NonFalsyStringValidator()],
            ),
            'non-empty-string' => new ConstraintNode(
                new StringNode(),
                [new NonEmptyString()],
            ),
            'scalar' => new UnionNode([
                new IntNode(),
                new FloatNode(),
                new BoolNode(),
                new StringNode(),
            ]),
            'positive-int' => new ConstraintNode(
                new IntNode(),
                [new LengthValidator(min: 1, including: true)]
            ),
            'negative-int' => new ConstraintNode(
                new IntNode(),
                [new LengthValidator(max: -1, including: true)]
            ),
            "non-negative-int" => new ConstraintNode(
                new IntNode(),
                [new LengthValidator(min: 0, including: true)]
            ),
            'non-positive-int' => new ConstraintNode(
                new IntNode(),
                [new LengthValidator(max: 0, including: true)]
            ),
            'numeric' => new UnionNode([
                new IntNode(),
                new FloatNode(),
            ]),
            default => $state->produceSyntaxError('Expected valid built-in type, got ' . $token->value),
        };
    }
}