<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Parsers;

use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Parser\Definition\TokenType;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Utils\Reflections;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class CustomClassParser implements Parser
{

    public function canParse(string $fullyQualifiedClassName, Token $token): bool
    {
        if (!class_exists($fullyQualifiedClassName)) {
            return false;
        }

        $reflection = new ReflectionClass($fullyQualifiedClassName);
        return $reflection->isUserDefined() && $reflection->isInstantiable();
    }

    /**
     * @param ReflectionProperty[]|ReflectionParameter[] $properties
     * @return list<PropertyNode>
     * @throws Throwable
     */
    private function createProperties(array $properties, TypeParser $parser, PropertyType $propertyType): array
    {
        return array_map(
            static function(ReflectionProperty|ReflectionParameter $property) use ($parser, $propertyType) {
                $type = Reflections::getDocBlockExtendedType($property);

                return new PropertyNode(
                    $property->name,
                    $parser->parse($type),
                    false,
                    $propertyType
                );
            },
            $properties
        );
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @return array<ReflectionParameter|ReflectionProperty>
     */
    private function findInputProperties(ReflectionClass $reflectionClass): array
    {
        $hasConstructor = $reflectionClass->getConstructor() !== null;
        if ($hasConstructor) {
            return $reflectionClass->getConstructor()->getParameters();
        }

        $publicSettableProperties = array_filter(
            $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC),
            fn(ReflectionProperty $property) => !$property->isStatic() && !$property->hasHooks(),
        );

        return array_values($publicSettableProperties);
    }

    /**
     * @throws ReflectionException|Throwable
     */
    public function parse(string $fullyQualifiedClassName, Token $token, TypeParser $parser): CustomCastingNode
    {
        $reflectionClass = new ReflectionClass($fullyQualifiedClassName);

        $hasConstructor = $reflectionClass->getConstructor() !== null;
        if (!$hasConstructor) {
            throw new RuntimeException("Only support classes with a constructor");
        }

        $inputProperties = $this->createProperties(
            $this->findInputProperties($reflectionClass),
            $parser,
            PropertyType::INPUT,
        );
        $outputProperties = $this->createProperties(
            $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC),
            $parser,
            PropertyType::OUTPUT,
        );

        return new CustomCastingNode(
            new StructNode(StructPhpType::ARRAY, [
                ...$inputProperties,
                ...$outputProperties,
            ]),
            $fullyQualifiedClassName,
            ObjectCastStrategy::CONSTRUCTOR,
        );
    }
}