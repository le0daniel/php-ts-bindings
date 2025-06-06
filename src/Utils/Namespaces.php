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
                $map[Strings::classBaseName($alias)] = $alias;
            } else {
                $map[$alias] = $namespace;;
            }
        }
        return $map;
    }
}