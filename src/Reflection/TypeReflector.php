<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Utils\PhpDoc;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

final readonly class TypeReflector
{
    public static function reflectProperty(ReflectionProperty $property): string
    {
        if (!$property->getType()) {
            throw new RuntimeException("No type defined.");
        }

        if ($property->getDocComment() && $type = PhpDoc::findFirstVarDeclaration($property->getDocComment())) {
            return trim($type);
        }

        if (!$property->isPromoted()) {
            return (string)$property->getType();
        }

        $constructorDocBlock = $property->getDeclaringClass()->getConstructor()?->getDocComment();
        if ($constructorDocBlock && $type = PhpDoc::findParamWithNameDeclaration($constructorDocBlock, $property->getName())) {
            return trim($type);
        }

        return (string)$property->getType();
    }

    public static function reflectParameter(ReflectionParameter $parameter): string
    {
        if (!$parameter->getType()) {
            throw new RuntimeException("No type defined.");
        }

        $declaringDocBlock = $parameter->getDeclaringFunction()->getDocComment();
        if (!$declaringDocBlock) {
            return (string)$parameter->getType();
        }

        return trim(
            PhpDoc::findParamWithNameDeclaration($declaringDocBlock, $parameter->getName()) ?? (string)$parameter->getType()
        );
    }

    public static function reflectReturnType(ReflectionFunction|ReflectionMethod $returnable): string
    {
        if (!$returnable->hasReturnType()) {
            throw new RuntimeException("No return type defined.");
        }

        $docBlock = $returnable->getDocComment();
        if (!$docBlock) {
            return (string) $returnable->getReturnType();
        }

        return trim(
            PhpDoc::findReturnTypeDeclaration($docBlock) ?? (string) $returnable->getReturnType()
        );
    }
}