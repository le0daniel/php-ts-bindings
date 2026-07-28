<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BackingType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\AttributesReflector;
use ReflectionClass;
use ReflectionException;

/**
 * Consumes user defined value objects: classes implementing StringValueObject or IntValueObject.
 *
 * Registered ahead of EnumConsumer, DateTimeConsumer and UserDefinedObjectConsumer, all of which
 * would otherwise claim the class first.
 */
final class ValueObjectConsumer implements TypeConsumer
{
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);

        return is_a($fullyQualifiedClassName, StringValueObject::class, true)
            || is_a($fullyQualifiedClassName, IntValueObject::class, true);
    }

    /**
     * @throws ReflectionException
     */
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);

        $isStringBacked = is_a($fullyQualifiedClassName, StringValueObject::class, true);
        $isIntBacked = is_a($fullyQualifiedClassName, IntValueObject::class, true);

        // Validate before advancing, so the syntax error highlights the offending token.
        if ($isStringBacked && $isIntBacked) {
            $state->produceSyntaxError(
                "Value object {$fullyQualifiedClassName} must implement either StringValueObject or IntValueObject, not both."
            );
        }

        $reflectionClass = new ReflectionClass($fullyQualifiedClassName);
        if ($reflectionClass->isAbstract() || $reflectionClass->isInterface()) {
            $state->produceSyntaxError(
                "Value object {$fullyQualifiedClassName} must be instantiable. Abstract classes and interfaces are not supported."
            );
        }

        $state->advance();

        /** @var class-string<StringValueObject|IntValueObject> $fullyQualifiedClassName */
        return new ValueObjectNode(
            $fullyQualifiedClassName,
            $isStringBacked ? BackingType::STRING : BackingType::INT,
            $this->resolveBrand($reflectionClass),
        );
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function resolveBrand(ReflectionClass $reflectionClass): ?string
    {
        $attributes = new AttributesReflector($reflectionClass->getAttributes());
        if (!$attributes->has(Brand::class)) {
            return null;
        }

        // Without an explicit name, the brand is lcfirst of the base class name: UserId -> "userId".
        return $attributes->getSingleInstance(Brand::class)->name
            ?? lcfirst($reflectionClass->getShortName());
    }
}
