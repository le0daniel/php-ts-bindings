<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Helpers\Consumers;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BackingType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\MetadataAttributes;
use Override;
use ReflectionClass;
use ReflectionException;

/**
 * Consumes user-defined value objects: classes implementing StringValueObject or IntValueObject.
 *
 * Registered ahead of EnumConsumer, DateTimeConsumer and UserDefinedObjectConsumer, all of which
 * would otherwise claim the class first.
 */
final readonly class ValueObjectConsumer implements TypeConsumer
{
    #[Override]
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
    #[Override]
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

        if (!class_exists($fullyQualifiedClassName) && !interface_exists($fullyQualifiedClassName)) {
            $state->produceSyntaxError("Value object {$fullyQualifiedClassName} does not exist.");
        }

        $reflectionClass = new ReflectionClass($fullyQualifiedClassName);
        if ($reflectionClass->isAbstract() || $reflectionClass->isInterface()) {
            $state->produceSyntaxError(
                "Value object {$fullyQualifiedClassName} must be instantiable. Abstract classes and interfaces are not supported."
            );
        }

        $state->advance();

        /** @var class-string<StringValueObject|IntValueObject> $fullyQualifiedClassName */
        return MetadataAttributes::wrap(
            new ValueObjectNode(
                $fullyQualifiedClassName,
                $isStringBacked ? BackingType::STRING : BackingType::INT,
            ),
            $reflectionClass,
            // A family of ids usually shares one interface or base class; let it carry the
            // declaration for all of them. Only value objects opt in: an interface or abstract
            // parent is never parseable on its own here, so the attributes cannot mean anything
            // other than "apply to my children".
            inheritFromParents: true,
        );
    }
}
