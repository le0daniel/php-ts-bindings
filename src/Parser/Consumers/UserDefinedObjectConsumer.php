<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Consumers;

use Le0daniel\PhpTsBindings\Contracts\Constraint;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
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

    public function canConsume(ParserState $state): bool
    {
        if (!$state->currentTokenIs(TokenType::IDENTIFIER)) {
            return false;
        }

        $fqcn = $state->context->toFullyQualifiedClassName($state->current()->value);

        if (!class_exists($fqcn)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($fqcn);
        return new ReflectionClass($fqcn)->isUserDefined() && $reflectionClass->isInstantiable();
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

        $assignedGenerics = $this->consumeGenerics($state, $parser);
        $context = ParsingContext::fromClassReflection($reflectionClass, $assignedGenerics);

        $hasConstructor = $reflectionClass->getConstructor() !== null;
        if ($hasConstructor) {
            return $this->parseConstructorStrategy($reflectionClass, $parser, $context);
        }

        $properties = [];
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isReadOnly() || $property->hasHooks()) {
                throw new RuntimeException("Property {$property->name} is not writable");
            }

            $properties[] = new PropertyNode(
                $property->getName(),
                $this->applyConstraints($property, $parser->parse(TypeReflector::reflectProperty($property), $context)),
                false,
                PropertyType::BOTH,
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
                $this->applyConstraints($parameter, $parser->parse(TypeReflector::reflectParameter($parameter), $context)),
                false,
                PropertyType::INPUT,
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
                $this->applyConstraints($property, $parser->parse(TypeReflector::reflectProperty($property), $context)),
                false,
                PropertyType::OUTPUT,
            );
        }

        return new CustomCastingNode(
            new StructNode(StructPhpType::ARRAY, $structProperties),
            $reflectionClass->getName(),
            ObjectCastStrategy::CONSTRUCTOR,
        );
    }
}