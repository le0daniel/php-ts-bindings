<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final class Namespaces
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
     * @param array<int, class-string>|array<class-string, string> $namespaces
     * @return array<string, class-string>
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
     * @param array<string, class-string> $namespacesMap
     */
    public static function toFullyQualifiedClassName(string $className, ?string $namespace, array $namespacesMap): string
    {
        if (str_starts_with($className, '\\')) {
            return self::withoutLeadingSlash($className);
        }

        $lookupKey = explode('\\', $className)[0];
        if (array_key_exists($lookupKey, $namespacesMap)) {
            return $namespacesMap[$lookupKey] . '\\' . $className;
        }

        if ($namespace !== null) {
            return $namespace . '\\' . $className;
        }

        return $className;
    }
}