<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use ReflectionClass;
use Throwable;
use UnitEnum;

final class ClassConstConsumer implements TypeConsumer
{

    public function canConsume(ParserState $state): bool
    {
        return $state->currentTokenIs(TokenType::CLASS_CONST);
    }

    /** @throws InvalidSyntaxException */
    public function consume(ParserState $state, TypeParser $parser): LiteralNode
    {
        $token = $state->current();
        [$className, $constOrEnumCase] = explode('::', $token->value);
        $fqcn = $state->context->toFullyQualifiedClassName($className);

        try {
            $reflection = new ReflectionClass($fqcn);
            $const = $reflection->getConstant($constOrEnumCase);
            $isEnum = $const instanceof UnitEnum;
            $state->advance();

            return new LiteralNode(
                $isEnum ? LiteralType::ENUM_CASE : LiteralType::identifyPrimitiveTypeValue($const),
                $const
            );
        } catch (Throwable $exception) {
            $state->produceSyntaxError("Could not identify class const or enum", $exception);
        }
    }
}