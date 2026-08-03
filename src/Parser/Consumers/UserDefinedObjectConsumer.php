<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Optional;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Lexer\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\AttributesReflector;
use Le0daniel\PhpTsBindings\Reflection\MetadataAttributes;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Override;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

final readonly class UserDefinedObjectConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    public function __construct(
        public readonly bool $allowAllObjectCasting = false
    )
    {
    }

    #[Override]
    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);
        if (!class_exists($fullyQualifiedClassName) && !interface_exists($fullyQualifiedClassName)) {
            return false;
        }

        return new ReflectionClass($fullyQualifiedClassName)->isUserDefined();
    }

    /** @param ReflectionClass<object> $class */
    private function determineCastingStrategy(ReflectionClass $class): ObjectCastStrategy
    {
        if ($class->isAbstract()) {
            return ObjectCastStrategy::NEVER;
        }

        $attributes = new AttributesReflector($class->getAttributes());

        if ($attributes->has(Castable::class)) {
            $instance = $attributes->getSingleInstance(Castable::class);
            return $instance->strategy ?? $this->findCastingStrategy($class);
        }

        if (!$this->allowAllObjectCasting) {
            return ObjectCastStrategy::NEVER;
        }

        return $this->findCastingStrategy($class);
    }

    /**
     * @param ReflectionClass<object> $class
     * @return ObjectCastStrategy
     */
    private function findCastingStrategy(ReflectionClass $class): ObjectCastStrategy
    {
        $hasConstructor = $class->getConstructor() !== null;
        if ($hasConstructor) {
            return ObjectCastStrategy::CONSTRUCTOR;
        }

        return ObjectCastStrategy::ASSIGN_PROPERTIES;
    }

    /**
     * @throws ReflectionException
     * @throws InvalidSyntaxException
     */
    #[Override]
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);
        // canConsume() ran first and only claims names that resolve to a user defined class.
        assert(class_exists($fullyQualifiedClassName) || interface_exists($fullyQualifiedClassName));
        $state->advance();

        $reflectionClass = new ReflectionClass($fullyQualifiedClassName);
        $castingStrategy = $this->determineCastingStrategy($reflectionClass);

        $context = ParsingScope::fromReflectionClass($reflectionClass, $this->consumeGenerics($state, $parser));

        $node = match ($castingStrategy) {
            ObjectCastStrategy::NEVER => $this->parseNeverStrategy($reflectionClass, $parser, $context),
            ObjectCastStrategy::ASSIGN_PROPERTIES => $this->parseSetPropertiesStrategy($reflectionClass, $parser, $context),
            ObjectCastStrategy::CONSTRUCTOR => $this->parseConstructorStrategy($reflectionClass, $parser, $context),
        };

        return MetadataAttributes::wrap($node, $reflectionClass);
    }

    private function allowsOptional(ReflectionProperty|ReflectionParameter $param): bool
    {
        if (empty($param->getAttributes(Optional::class))) {
            return false;
        }

        $hasDefaultValue = match ($param::class) {
            ReflectionParameter::class => $param->isDefaultValueAvailable(),
            ReflectionProperty::class => $param->hasDefaultValue(),
            default => false,
        };

        if ($hasDefaultValue) {
            return true;
        }

        $type = $param->getType();
        if ($type === null || !$type->allowsNull()) {
            throw new ParserException("Optional parameter must allow null or provide a default value. PHP does not difference between null and undefined.");
        }

        return true;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    private function parseNeverStrategy(ReflectionClass $reflectionClass, TypeParser $parser, ParsingScope $context): CustomCastingNode
    {
        $properties = array_map(
            fn(ReflectionProperty $property) => new PropertyNode(
                $property->getName(),
                $parser->parse(
                    TypeReflector::reflectProperty($property),
                    $context->descendIntoDeclaringClass($property)
                ),
                false,
                PropertyType::OUTPUT,
            ),
            $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC),
        );

        return new CustomCastingNode(
            new StructNode(
                StructPhpType::ARRAY,
                $properties,
            ),
            $reflectionClass->getName(),
            ObjectCastStrategy::NEVER,
        );
    }

    /** @param ReflectionClass<object> $reflectionClass */
    private function parseSetPropertiesStrategy(ReflectionClass $reflectionClass, TypeParser $parser, ParsingScope $context): CustomCastingNode
    {
        $properties = [];
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isReadOnly() || $property->hasHooks()) {
                throw new ParserException("Property {$property->name} is not writable");
            }

            $properties[] = new PropertyNode(
                $property->getName(),
                $parser->parse(
                    TypeReflector::reflectProperty($property),
                    $context->descendIntoDeclaringClass($property)
                ),
                isOptional: $this->allowsOptional($property),
                propertyType: PropertyType::BOTH,
            );
        }

        return new CustomCastingNode(
            new StructNode(StructPhpType::ARRAY, $properties),
            $reflectionClass->getName(),
            ObjectCastStrategy::ASSIGN_PROPERTIES,
        );
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @throws InvalidSyntaxException
     */
    private function parseConstructorStrategy(ReflectionClass $reflectionClass, TypeParser $parser, ParsingScope $context): CustomCastingNode
    {
        /** @var array<PropertyNode> $structProperties */
        $structProperties = [];

        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            throw new ParserException(
                "Cannot build {$reflectionClass->getName()} from its constructor: it declares none."
            );
        }

        foreach ($constructor->getParameters() as $parameter) {
            $structProperties[] = new PropertyNode(
                $parameter->name,
                $parser->parse(
                    TypeReflector::reflectParameter($parameter),
                    $context->descendIntoDeclaringClass($parameter)
                ),
                isOptional: $this->allowsOptional($parameter),
                propertyType: PropertyType::INPUT,
            );
        }

        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isPromoted()) {
                $index = array_find_key($structProperties, fn(PropertyNode $propertyNode) => $propertyNode->name === $property->getName());
                if ($index !== null) {
                    $structProperties[$index] = $structProperties[$index]->changePropertyType(PropertyType::BOTH);
                }
                continue;
            }

            $structProperties[] = new PropertyNode(
                $property->name,
                $parser->parse(
                    TypeReflector::reflectProperty($property),
                    $context->descendIntoDeclaringClass($property)
                ),
                isOptional: $this->allowsOptional($property),
                propertyType: PropertyType::OUTPUT,
            );
        }

        return new CustomCastingNode(
            new StructNode(StructPhpType::ARRAY, array_values($structProperties)),
            $reflectionClass->getName(),
            ObjectCastStrategy::CONSTRUCTOR,
        );
    }
}