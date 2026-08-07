<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final readonly class Namespaces
{
    /**
     * Example Namespaces:
     *  ```
     *   [
     *       'App\Models',
     *       'App\Models\User',
     *       // Namespace Aliases
     *       'App\Contracts\User' => 'UserContract',
     *   ]
     *  ```
     *
     * Will Return
     * ```
     *  [
     *      'models' => 'App\Models',
     *      'user' => 'App\Models\User',
     *      'usercontract' => 'App\Contracts\User',
     *  ]
     * ```
     *
     * Keys are lowercased because PHP resolves `use` aliases case insensitively.
     *
     * Names come from a file's parsed `use` statements, so they are strings that look like class
     * names but are not verified to name anything. Typing them class-string would be a guarantee
     * this cannot make - resolution happens later, against the consumers that actually need a class.
     *
     * @param  array<int|string, string>  $namespaces
     * @return array<string, string>
     */
    public static function buildNamespaceAliasMap(array $namespaces): array
    {
        $map = [];
        foreach ($namespaces as $namespace => $alias) {
            if (is_int($namespace)) {
                $map[strtolower(Strings::classBaseName($alias))] = self::withoutLeadingSlash($alias);
            } else {
                $map[strtolower($alias)] = self::withoutLeadingSlash($namespace);
            }
        }

        return $map;
    }

    private static function withoutLeadingSlash(string $className): string
    {
        return str_starts_with($className, '\\') ? substr($className, 1) : $className;
    }

    /**
     * PHP's name resolution and nothing else, matching PHPStan's NameScope::resolveStringName().
     *
     * There is deliberately no "this already looks fully qualified" check: a qualified name whose
     * first segment is not imported is relative, however absolute it looks, and guessing otherwise
     * makes resolution depend on which unrelated classes a file happens to import. Reflection is the
     * one source that hands over names that really are absolute, and TypeReflector marks those with
     * a leading backslash before they ever get here.
     *
     * @param  array<string, string>  $namespacesMap
     */
    public static function toFullyQualifiedClassName(string $className, ?string $namespace, array $namespacesMap): string
    {
        if (str_starts_with($className, '\\')) {
            return self::withoutLeadingSlash($className);
        }

        // The map holds alias => the full name it was imported as, so only the segments *after*
        // the alias are appended: `use App\Models;` plus `Models\User` is App\Models\User, not
        // App\Models\Models\User.
        $segments = explode('\\', $className);
        $lookupKey = strtolower($segments[0]);
        if (array_key_exists($lookupKey, $namespacesMap)) {
            $remaining = array_slice($segments, 1);

            return $remaining === []
                ? $namespacesMap[$lookupKey]
                : $namespacesMap[$lookupKey].'\\'.implode('\\', $remaining);
        }

        return $namespace === null ? $className : $namespace.'\\'.$className;
    }
}
