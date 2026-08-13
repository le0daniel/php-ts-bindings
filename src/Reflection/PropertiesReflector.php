<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use PropertyHookType;
use ReflectionProperty;

final readonly class PropertiesReflector
{
    public static function isWritableFromPublicScope(ReflectionProperty $property): bool
    {
        if (!$property->isPublic()) {
            return false;
        }

        if ($property->isReadOnly()) {
            return false;
        }

        if ($property->isProtectedSet() || $property->isPrivateSet()) {
            return false;
        }

        // For virtual hooked properties, a set hook is required to be writable.
        if ($property->isVirtual()) {
            return $property->hasHook(PropertyHookType::Set);
        }

        return true;
    }

    public static function isReadableFromPublicScope(ReflectionProperty $property): bool
    {
        if (!$property->isPublic()) {
            return false;
        }

        if ($property->isVirtual()) {
            return $property->hasHook(PropertyHookType::Get);
        }

        return true;
    }
}
