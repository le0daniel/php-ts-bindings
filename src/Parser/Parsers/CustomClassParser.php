<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Parsers;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Nodes\UserDefinedObject;
use Le0daniel\PhpTsBindings\Parser\Token;
use Le0daniel\PhpTsBindings\Parser\TokenType;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

final class CustomClassParser implements Parser
{

    public function canParse(Token $token): bool
    {
        if (!$token->is(TokenType::IDENTIFIER) ) {
            return false;
        }

        $typeName = $token->value;
        if (!class_exists($typeName)) {
            return false;
        }

        $reflection = new ReflectionClass($typeName);
        return $reflection->isUserDefined() && !$reflection->isAbstract();
    }

    /**
     * @param ReflectionProperty[]|ReflectionParameter[] $properties
     * @return array<string, NodeInterface>
     */
    private function createProperties(array $properties, TypeParser $parser): array
    {
        $parsed = [];

        foreach ($properties as $property) {
            if (!$property->hasType()) {
                throw new RuntimeException("Property '{$property->name}' of {$property->class} does not have any type defined");
            }

            $parsed[$property->name] = $parser->parse($property->getType()->getName());
        }

        return $parsed;
    }

    /**
     * @param ReflectionClass $reflection
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

    public function parse(Token $token, TypeParser $parser): UserDefinedObject
    {
        $className = $token->value;
        $reflectionClass = new ReflectionClass($className);
        $hasConstructor = $reflectionClass->getConstructor() !== null;

        if (!$hasConstructor) {
            throw new RuntimeException("Only support classes with a constructor");
        }

        $inputProperties = $this->createProperties($this->findInputProperties($reflectionClass), $parser);
        $outputProperties = $this->createProperties($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC), $parser);
        return new UserDefinedObject($className, $inputProperties, $outputProperties);
    }
}