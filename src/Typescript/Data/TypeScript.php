<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Data;

/**
 * A generated TypeScript type together with the aliases it references.
 */
final readonly class TypeScript
{
    /**
     * @param string $type The type. Branded leaves are always referenced by their alias name.
     * @param TypeRegistry $registry The aliases $type refers to, e.g.
     *        ['Email' => 'string & Brand<"email">']. A consumer emits each entry as
     *        `export type {$alias} = {$definition}`.
     */
    public function __construct(
        public string       $type,
        public TypeRegistry $registry = new TypeRegistry(),
    )
    {
    }

    /**
     * The same type with every alias replaced by its definition, so it can be used on its own
     * without also emitting the declarations from $registry.
     *
     * strtr() rather than str_replace(): it substitutes in a single pass, longest alias first, and
     * never rescans what it just wrote. str_replace() would let `User` corrupt `UserId`, and an
     * alias named `Brand` would eat the `Brand<"...">` of a definition inserted moments earlier.
     */
    public function toStandaloneType(): string
    {
        return strtr($this->type, $this->registry->toArray());
    }
}
