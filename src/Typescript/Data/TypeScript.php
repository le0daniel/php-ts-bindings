<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Data;

/**
 * A generated TypeScript type together with the aliases it references.
 */
final readonly class TypeScript
{
    /**
     * @param string $type The type. Named types are referenced by their alias name, brands appear
     *        inline as `(... & Brand<"...">)`.
     * @param TypeRegistry $registry Every alias this emission produced, e.g.
     *        ['Order' => '{id:(number & Brand<"orderId">);}']. A consumer emits each entry as
     *        `export type {$alias} = {$definition}`.
     */
    public function __construct(
        public string       $type,
        public TypeRegistry $registry
    )
    {
    }

    public static function fromRawString(string $type): TypeScript
    {
        return new TypeScript($type, new TypeRegistry());
    }
}
