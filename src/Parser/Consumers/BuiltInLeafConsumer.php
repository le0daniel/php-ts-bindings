<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
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
            'int',
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
        ], true);
    }

    /**
     * @throws InvalidSyntaxException
     */
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $token = $state->current();
        $state->advance();

        return match ($token->value) {
            'int' => $this->consumeIntWithGenerics($state, $parser),
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

    private function consumeIntWithGenerics(ParserState $state, TypeParser $parser): NodeInterface
    {
        if (!$state->currentTokenIs(TokenType::LT)) {
            return new BuiltInNode(BuiltInType::INT);
        }

        $state->advance();
        $min = match (true) {
            $state->currentTokenIs(TokenType::INT) => (int)$state->current()->value,
            $state->currentTokenIs(TokenType::IDENTIFIER, 'min') => PHP_INT_MIN,
            default => $state->produceSyntaxError('Expected int or min'),
        };

        $state->advance();
        if (!$state->currentTokenIs(TokenType::COMMA)) {
            $state->produceSyntaxError("Expected comma");
        }
        $state->advance();

        $max = match (true) {
            $state->currentTokenIs(TokenType::INT) => (int)$state->current()->value,
            $state->currentTokenIs(TokenType::IDENTIFIER, 'max') => PHP_INT_MAX,
            default => $state->produceSyntaxError('Expected int or max'),
        };

        $state->advance();
        if (!$state->current()->is(TokenType::GT)) {
            $state->produceSyntaxError("Expected >");
        }

        $state->advance();

        return new ConstraintNode(
            new BuiltInNode(BuiltInType::INT),
            [new LengthValidator(min: $min, max: $max, including: true)]
        );
    }
}