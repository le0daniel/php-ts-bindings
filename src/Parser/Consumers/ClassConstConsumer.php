<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Override;
use ReflectionClass;
use Throwable;
use UnitEnum;

final readonly class ClassConstConsumer implements TypeConsumer
{

    /**
     * `Foo::BAR` used to arrive as a single CLASS_CONST token. The lexer no longer merges
     * it, so this matches the three token sequence instead. The peek(2) guard keeps a
     * trailing `Foo::` from being claimed here, and keeps this consumer — which runs ahead
     * of the alias, enum and object consumers — from stealing plain identifiers.
     */
    #[Override]
    public function canConsume(ParserState $state): bool
    {
        return $state->currentTokenIs(TokenType::IDENTIFIER)
            && $state->nextTokenIs(TokenType::DOUBLE_COLON)
            && $state->peek(2)?->is(TokenType::IDENTIFIER) === true;
    }

    /** @throws InvalidSyntaxException */
    #[Override]
    public function consume(ParserState $state, TypeParser $parser): LiteralNode
    {
        $className = $state->current()->value;
        $state->advance(2);

        $constOrEnumCase = $state->current()->value;
        $fqcn = $state->context->toFullyQualifiedClassName($className);
        if (!class_exists($fqcn) && !interface_exists($fqcn)) {
            $state->produceSyntaxError("Class {$fqcn} does not exist.");
        }

        try {
            $reflection = new ReflectionClass($fqcn);
            if (!$reflection->hasConstant($constOrEnumCase)) {
                $state->produceSyntaxError("Class {$fqcn} has no constant or enum case {$constOrEnumCase}");
            }

            $const = $reflection->getConstant($constOrEnumCase);
            $isEnum = $const instanceof UnitEnum;
            $state->advance();

            return new LiteralNode(
                $isEnum ? LiteralType::ENUM_CASE : LiteralType::identifyPrimitiveTypeValue($const),
                $const
            );
        } catch (InvalidSyntaxException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $state->produceSyntaxError("Could not identify class const or enum", $exception);
        }
    }
}