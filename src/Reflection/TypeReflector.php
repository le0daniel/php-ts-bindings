<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Utils\Regexes;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final readonly class TypeReflector
{
    public static function reflectProperty(ReflectionProperty $property): string
    {
        $type = $property->getType();
        if (! $type) {
            throw new ParserException('No type defined.');
        }

        if ($property->getDocComment() && $docBlockType = Regexes::findFirstVarDeclaration($property->getDocComment())) {
            return trim($docBlockType);
        }

        if (! $property->isPromoted()) {
            return self::toTypeString($type);
        }

        $constructorDocBlock = $property->getDeclaringClass()->getConstructor()?->getDocComment();
        if ($constructorDocBlock && $docBlockType = Regexes::findParamWithNameDeclaration($constructorDocBlock, $property->getName())) {
            return trim($docBlockType);
        }

        return self::toTypeString($type);
    }

    public static function reflectParameter(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        if (! $type) {
            throw new ParserException('No type defined.');
        }

        $declaringDocBlock = $parameter->getDeclaringFunction()->getDocComment();
        if (! $declaringDocBlock) {
            return self::toTypeString($type);
        }

        $docBlockType = Regexes::findParamWithNameDeclaration($declaringDocBlock, $parameter->getName());

        return $docBlockType === null ? self::toTypeString($type) : trim($docBlockType);
    }

    public static function reflectReturnType(ReflectionFunction|ReflectionMethod $returnable): string
    {
        $type = $returnable->getReturnType();
        if (! $type) {
            throw new ParserException('No return type defined.');
        }

        $docBlock = $returnable->getDocComment();
        if (! $docBlock) {
            return self::toTypeString($type);
        }

        $docBlockType = Regexes::findReturnTypeDeclaration($docBlock);

        return $docBlockType === null ? self::toTypeString($type) : trim($docBlockType);
    }

    /**
     * Reflection reports class names fully qualified but drops the leading backslash that says so,
     * leaving `App\Models\User` indistinguishable from a name the parser still has to resolve
     * against the declaring file's namespace and imports - which is how it ended up resolving to
     * `Current\Namespace\App\Models\User`. Putting the backslash back marks the name as absolute.
     *
     * This mirrors how PHPStan hands a native type to its own parser: it never round-trips through
     * a string, it emits a FullyQualified node straight from the ReflectionType.
     */
    private static function toTypeString(ReflectionType $type): string
    {
        return match (true) {
            $type instanceof ReflectionUnionType => implode('|', array_map(
                // Reflection reports a DNF type as a union with an intersection member. Without the
                // parentheses `(A&B)|null` would read back as `A & (B|null)`.
                fn (ReflectionType $member): string => $member instanceof ReflectionIntersectionType
                    ? '('.self::toTypeString($member).')'
                    : self::toTypeString($member),
                $type->getTypes(),
            )),
            $type instanceof ReflectionIntersectionType => implode(
                '&',
                array_map(self::toTypeString(...), $type->getTypes()),
            ),
            $type instanceof ReflectionNamedType => self::namedTypeToString($type),
            default => (string) $type,
        };
    }

    private static function namedTypeToString(ReflectionNamedType $type): string
    {
        $name = $type->getName();

        // `self` and `parent` are resolved by PHP itself, so `static` is the only non-builtin name
        // reflection reports that does not name a class.
        $qualified = $type->isBuiltin() || $name === 'static' ? $name : "\\{$name}";

        // mixed and null carry allowsNull() on their own; neither `?mixed` nor `?null` is a type.
        return $type->allowsNull() && $name !== 'mixed' && $name !== 'null'
            ? "?{$qualified}"
            : $qualified;
    }
}
