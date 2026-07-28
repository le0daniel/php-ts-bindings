<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Data;

final readonly class Options
{
    /**
     * @param bool $pretty Break object literals across lines and space out separators.
     * @param TypeRegistry $registry Aliases already known to the caller, so several types can be
     *        generated against one shared set. It is never mutated: generation works on a copy and
     *        hands that copy back in TypeScript::$registry.
     */
    public function __construct(
        public bool         $pretty = false,
        public TypeRegistry $registry = new TypeRegistry(),
    )
    {
    }
}
