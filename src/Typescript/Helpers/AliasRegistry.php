<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Helpers;

use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnknownAliasException;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;

/**
 * The named TypeScript types a generated type refers to: alias => definition.
 *
 * Today every entry comes from a brand (`Email` => `(string & Brand<"email">)`), but nothing here is
 * brand specific. Any future construct that wants to be emitted once and referenced by name — a
 * shared enum, a deduplicated object shape — belongs in the same registry.
 *
 * Not to be confused with Parser\Contracts\TypeRegistry, which is the AST optimizer's node cache.
 */
final class AliasRegistry
{
    /** @var array<string, string> */
    private array $definitions = [];

    /**
     * @param  array<string, string>  $definitions  Alias => definition.
     */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $alias => $definition) {
            $this->set($alias, $definition);
        }
    }

    /**
     * An alias names exactly one type. Two definitions claiming the same alias would generate two
     * conflicting `export type` statements, so the collision is rejected here rather than emitted
     * as broken TypeScript. Registering the identical definition twice is fine.
     */
    public function set(string $alias, string $definition): void
    {
        $existing = $this->definitions[$alias] ?? null;
        if ($existing !== null && $existing !== $definition) {
            throw UnsupportedTypeException::conflictingAlias($alias, $existing, $definition);
        }

        $this->definitions[$alias] = $definition;
    }

    public function has(string $alias): bool
    {
        return isset($this->definitions[$alias]);
    }

    /**
     * Call has() first: an unknown alias is a programming error, not a miss to be handled.
     */
    public function get(string $alias): string
    {
        return $this->definitions[$alias]
            ?? throw UnknownAliasException::forAlias($alias, array_keys($this->definitions));
    }

    public function isEmpty(): bool
    {
        return $this->definitions === [];
    }

    /**
     * Every alias in here counts as used: the registry travels with the generated type and only
     * ever contains what that type relies on.
     *
     * @return list<string> sorted, so import statements are deterministic
     */
    public function usedAliases(): array
    {
        $aliases = array_keys($this->definitions);
        sort($aliases);

        return $aliases;
    }

    /**
     * Sorted by alias, so the same schema always generates byte identical output.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return $definitions;
    }
}
