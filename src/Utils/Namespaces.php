<?php declare(strict_types=1);

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
     *      'Models' => 'App\Models',
     *      'User' => 'App\Models\User',
     *      'UserContract' => 'App\Contracts\User',
     *  ]
     * ```
     *
     * Names come from a file's parsed `use` statements, so they are strings that look like class
     * names but are not verified to name anything. Typing them class-string would be a guarantee
     * this cannot make - resolution happens later, against the consumers that actually need a class.
     *
     * @param array<int|string, string> $namespaces
     * @return array<string, string>
     */
    public static function buildNamespaceAliasMap(array $namespaces): array
    {
        $map = [];
        foreach ($namespaces as $namespace => $alias) {
            if (is_int($namespace)) {
                $map[Strings::classBaseName($alias)] = self::withoutLeadingSlash($alias);
            } else {
                $map[$alias] = self::withoutLeadingSlash($namespace);
            }
        }
        return $map;
    }

    private static function withoutLeadingSlash(string $className): string
    {
        return str_starts_with($className, '\\') ? substr($className, 1) : $className;
    }

    /**
     * @param array<string, string> $namespacesMap
     */
    public static function toFullyQualifiedClassName(string $className, ?string $namespace, array $namespacesMap): string
    {
        if (str_starts_with($className, '\\')) {
            return self::withoutLeadingSlash($className);
        }

        $lookupKey = explode('\\', $className)[0];
        if (array_key_exists($lookupKey, $namespacesMap)) {
            $classNameOrNameSpace = $namespacesMap[$lookupKey];
            return str_contains($className, '\\')
                ? $classNameOrNameSpace . '\\' . $className
                : $classNameOrNameSpace;
        }

        // If reflection->getType()->getName() is used, it already returns a fully qualified class name.
        // In case we did not find an import match, we check if the classname is imported anywhere already. If this is the case, we return it.
        if (array_any($namespacesMap, fn(string $usedClass) => str_starts_with($className, $usedClass))) {
            return $className;
        }

        if ($namespace !== null && !str_starts_with($className, $namespace)) {
            return $namespace . '\\' . $className;
        }

        return $className;
    }
}