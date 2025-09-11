<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Optional;
use Le0daniel\PhpTsBindings\Contracts\Attributes\OutputOnly;
use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Contracts\TypeConsumer;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Definition\ParserState;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\AttributesReflector;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

final class UserDefinedObjectConsumer implements TypeConsumer
{
    use InteractsWithGenerics;

    public function __construct(
        public readonly bool $allowAllObjectCasting = false
    )
    {
    }

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);

        if (!class_exists($fullyQualifiedClassName)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($fullyQualifiedClassName);
        return new ReflectionClass($fullyQualifiedClassName)->isUserDefined() && $reflectionClass->isInstantiable();
    }

    /** @param ReflectionClass<object> $class */
    private function determineCastingStrategy(ReflectionClass $class): ObjectCastStrategy
    {
        $attributes = new AttributesReflector($class->getAttributes());

        if ($attributes->has(OutputOnly::class)) {
            return ObjectCastStrategy::NEVER;
        }

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
    public function consume(ParserState $state, TypeParser $parser): NodeInterface
    {
        $fullyQualifiedClassName = $state->context->toFullyQualifiedClassName($state->current()->value);
        $state->advance();

        $reflectionClass = new ReflectionClass($fullyQualifiedClassName);
        $castingStrategy = $this->determineCastingStrategy($reflectionClass);

        $context = ParsingContext::fromReflectionClass($reflectionClass, $this->consumeGenerics($state, $parser));

        return match ($castingStrategy) {
            ObjectCastStrategy::NEVER => $this->parseNeverStrategy($reflectionClass, $parser, $context),
            ObjectCastStrategy::ASSIGN_PROPERTIES => $this->parseSetPropertiesStrategy($reflectionClass, $parser, $context),
            ObjectCastStrategy::CONSTRUCTOR => $this->parseConstructorStrategy($reflectionClass, $parser, $context),
            default => throw new RuntimeException("Casting strategy {$castingStrategy->name} is not supported"),
        };
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

        if (!$param->getType()->allowsNull()) {
            throw new RuntimeException("Optional parameter must allow null or provide a default value. PHP does not difference between null and undefined.");
        }

        return true;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    private function parseNeverStrategy(ReflectionClass $reflectionClass, TypeParser $parser, ParsingContext $context): CustomCastingNode
    {
        return new CustomCastingNode(
            new StructNode(
                StructPhpType::ARRAY,
                array_map(
                    fn(ReflectionProperty $property) => new PropertyNode(
                        $property->getName(),
                        $this->applyConstraints(
                            $property,
                            $parser->parse(
                                TypeReflector::reflectProperty($property),
                                $context->descendIntoDeclaringClass($property)
                            )
                        ),
                        false,
                        PropertyType::OUTPUT,
                    ),
                    $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC),
                ),
            ),
            $reflectionClass->getName(),
            ObjectCastStrategy::NEVER,
        );
    }

    /** @param ReflectionClass<object> $reflectionClass */
    private function parseSetPropertiesStrategy(ReflectionClass $reflectionClass, TypeParser $parser, ParsingContext $context): CustomCastingNode
    {
        $properties = [];
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isReadOnly() || $property->hasHooks()) {
                throw new RuntimeException("Property {$property->name} is not writable");
            }

            $properties[] = new PropertyNode(
                $property->getName(),
                $this->applyConstraints(
                    $property,
                    $parser->parse(
                        TypeReflector::reflectProperty($property),
                        $context->descendIntoDeclaringClass($property)
                    )
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

    private function applyConstraints(ReflectionProperty|ReflectionParameter $reflection, NodeInterface $node): NodeInterface
    {
        $constraints = Arrays::filterNullValues(
            array_map(
                static function (ReflectionAttribute $attribute): null|Constraint {
                    $instance = $attribute->newInstance();
                    return $instance instanceof Constraint ? $instance : null;
                },
                $reflection->getAttributes()
            )
        );

        return empty($constraints) ? $node : new ConstraintNode(
            $node,
            $constraints,
        );
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @throws InvalidSyntaxException
     */
    private function parseConstructorStrategy(ReflectionClass $reflectionClass, TypeParser $parser, ParsingContext $context): CustomCastingNode
    {
        /** @var array<PropertyNode> $structProperties */
        $structProperties = [];

        foreach ($reflectionClass->getConstructor()->getParameters() as $parameter) {
            $structProperties[] = new PropertyNode(
                $parameter->name,
                $this->applyConstraints(
                    $parameter,
                    $parser->parse(
                        TypeReflector::reflectParameter($parameter),
                        $context->descendIntoDeclaringClass($parameter)
                    )
                ),
                isOptional: $this->allowsOptional($parameter),
                propertyType: PropertyType::INPUT,
            );
        }

        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isPromoted()) {
                $index = array_find_key($structProperties, fn(PropertyNode $propertyNode) => $propertyNode->name === $property->getName());
                $structProperties[$index] = $structProperties[$index]->changePropertyType(PropertyType::BOTH);
                continue;
            }

            $structProperties[] = new PropertyNode(
                $property->name,
                $this->applyConstraints(
                    $property,
                    $parser->parse(
                        TypeReflector::reflectProperty($property),
                        $context->descendIntoDeclaringClass($property)
                    )
                ),
                isOptional: $this->allowsOptional($property),
                propertyType: PropertyType::OUTPUT,
            );
        }

        return new CustomCastingNode(
            new StructNode(StructPhpType::ARRAY, $structProperties),
            $reflectionClass->getName(),
            ObjectCastStrategy::CONSTRUCTOR,
        );
    }
}