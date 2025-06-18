<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
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
            'string',
            'bool',
            'null',
            'float',
            'mixed' => new BuiltInNode(BuiltInType::from($token->value)),
            'truthy-string',
            'non-falsy-string' => new ConstraintNode(
                new BuiltInNode(BuiltInType::STRING),
                [new NonFalsyStringValidator()],
            ),
            'non-empty-string' => new ConstraintNode(
                new BuiltInNode(BuiltInType::STRING),
                [new NonEmptyString()],
            ),
            'scalar' => new UnionNode([
                new BuiltInNode(BuiltInType::INT),
                new BuiltInNode(BuiltInType::FLOAT),
                new BuiltInNode(BuiltInType::BOOL),
                new BuiltInNode(BuiltInType::STRING),
            ]),
            'positive-int' => new ConstraintNode(
                new BuiltInNode(BuiltInType::INT),
                [new LengthValidator(min: 1, including: true)]
            ),
            'negative-int' => new ConstraintNode(
                new BuiltInNode(BuiltInType::INT),
                [new LengthValidator(max: -1, including: true)]
            ),
            "non-negative-int" => new ConstraintNode(
                new BuiltInNode(BuiltInType::INT),
                [new LengthValidator(min: 0, including: true)]
            ),
            'non-positive-int' => new ConstraintNode(
                new BuiltInNode(BuiltInType::INT),
                [new LengthValidator(max: 0, including: true)]
            ),
            'numeric' => new UnionNode([
                new BuiltInNode(BuiltInType::INT),
                new BuiltInNode(BuiltInType::FLOAT),
            ]),
            default => $state->produceSyntaxError('Expected valid built-in type, got ' . $token->value),
        };
    }
}