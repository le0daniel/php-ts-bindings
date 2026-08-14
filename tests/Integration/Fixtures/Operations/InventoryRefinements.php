<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;

/**
 * Every string and int refinement the parser knows, each on its own key so a failing value
 * surfaces exactly one issue at exactly one path. Refinements run on parse only.
 */
final class InventoryRefinements
{
    /**
     * All eight string refinements side by side.
     *
     * @param  array{amount: numeric-string, code: non-empty-string, comment: non-falsy-string, label: non-empty-uppercase-string, memo: truthy-string, slug: lowercase-string, tag: non-empty-lowercase-string, ticker: uppercase-string}  $input
     * @return array{ok: true}
     */
    #[Query('inventory')]
    public function qualityGate(array $input): array
    {
        return ['ok' => true];
    }

    /**
     * The four named int refinements plus both half-open range forms.
     *
     * @param  array{debt: non-positive-int, delta: int<min, 0>, drop: negative-int, floor: int<0, max>, growth: positive-int, level: non-negative-int}  $input
     * @return array{ok: true}
     */
    #[Query('inventory')]
    public function boundsCheck(array $input): array
    {
        return ['ok' => true];
    }
}
