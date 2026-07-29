<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Data;

use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;

/**
 * Everything a single walk needs, threaded through the recursion.
 *
 * @internal
 */
final readonly class EmissionContext
{
    public function __construct(
        public IO            $io,
        public AliasRegistry $registry,
    )
    {
    }
}
