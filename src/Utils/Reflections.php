<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

final class Reflections
{
    public static function getDocBlockExtendedType(ReflectionProperty|ReflectionParameter $propertyOrParameter): string
    {
        if (!$propertyOrParameter->getType()) {
            throw new RuntimeException("No type defined.");
        }

        $typeString = match (true) {
            $propertyOrParameter instanceof ReflectionProperty => self::getPropertyTypeString($propertyOrParameter),
            $propertyOrParameter instanceof ReflectionParameter => self::getParameterTypeString($propertyOrParameter),
        };
        return trim($typeString);
    }

    private static function getParameterTypeString(ReflectionParameter $parameter): string
    {
        if (!$parameter->getType()) {
            throw new RuntimeException("No type defined.");
        }

        $declaringFnDoc = $parameter->getDeclaringFunction()->getDocComment();
        if (!$declaringFnDoc) {
            return (string)$parameter->getType();
        }

        return Regexes::findParamWithNameDeclaration($declaringFnDoc, $parameter->getName()) ?? (string)$parameter->getType();
    }

    private static function getPropertyTypeString(ReflectionProperty $property): string
    {
        if (!$property->hasType()) {
            throw new RuntimeException("No type defined.");
        }

        if ($property->getDocComment()) {
            return Regexes::findFirstVarDeclaration($property->getDocComment()) ?? (string)$property->getType();
        }

        if (!$property->isPromoted()) {
            return (string)$property->getType();
        }

        $constructorDocBlock = $property->getDeclaringClass()->getConstructor()?->getDocComment();
        if (!$constructorDocBlock) {
            return (string)$property->getType();
        }

        return Regexes::findParamWithNameDeclaration($constructorDocBlock, $property->getName()) ?? (string)$property->getType();
    }

    public static function getReturnType(ReflectionMethod|ReflectionFunction $reflection): string
    {
        $docBlock = $reflection->getDocComment();
        if (!$docBlock) {
            return (string)$reflection->getReturnType();
        }

        return Regexes::findReturnTypeDeclaration($docBlock) ?? (string)$reflection->getReturnType();
    }
}