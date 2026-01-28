<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

final class LiteralConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        if ($state->current()->isAnyTypeOf(TokenType::BOOL, TokenType::STRING, TokenType::FLOAT, TokenType::INT)) {
            return true;
        }

        if ($state->currentTokenIs(TokenType::CLASS_CONST)) {
            return true;
        }

        return false;
    }

    public function consume(ParserState $state, TypeParser $parser): LiteralNode
    {
        $token = $state->current();
        $state->advance();

        if ($token->is(TokenType::CLASS_CONST)) {
            [$className, $constOrEnumCase] = explode('::', $token->value);
            $fqcn = $state->context->toFullyQualifiedClassName($className);

            try {
                $reflection = new \ReflectionClass($fqcn);
                $const = $reflection->getConstant($constOrEnumCase);
                $isEnum = $const instanceof \UnitEnum;

                return new LiteralNode(
                    $isEnum ? LiteralType::ENUM_CASE : LiteralType::identifyPrimitiveTypeValue($const),
                    $const
                );
            } catch (\Throwable $exception) {
                $state->produceSyntaxError("Could not identify class const or enum", $exception);
            }
        }

        return new LiteralNode(
            LiteralType::identifyPrimitiveTypeValue($token->coercedValue()),
            $token->coercedValue(),
        );
    }
}