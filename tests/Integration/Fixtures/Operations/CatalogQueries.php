<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Integration\Fixtures\DataShapes\Paginated;
use Tests\Integration\Fixtures\Types\CatalogShared;
use Tests\Integration\Fixtures\Types\Money;
use Tests\Integration\Fixtures\Types\OrderStatus;
use Tests\Integration\Fixtures\Types\Sku;

/**
 * Collection shapes and alias resolution: postfix arrays, keyed tuples, nullable containers,
 * intersections, and the three alias kinds (local, imported-with-rename, global).
 *
 * @phpstan-type SkuList non-empty-list<Sku>
 * @phpstan-import-type PriceRange from CatalogShared as Range
 */
final class CatalogQueries
{
    /**
     * Postfix array syntax in both directions, including a doubly nested int[][].
     *
     * @param  array{grid: int[][], sku: Sku}  $input
     * @return array{codes: Sku[], grid: int[][]}
     */
    #[Query('catalog')]
    public function relatedSkus(array $input): array
    {
        return ['codes' => [$input['sku']], 'grid' => $input['grid']];
    }

    /**
     * A non-empty record: stays a JSON object and rejects {} on parse.
     *
     * @param  array{thresholds: non-empty-array<string, int>}  $input
     * @return array{thresholds: non-empty-array<string, int>}
     */
    #[Query('catalog')]
    public function priceBuckets(array $input): array
    {
        return $input;
    }

    /**
     * An int-keyed record: JSON object keys arrive as strings, PHP folds numeric ones to ints
     * before the handler sees them, and a non-numeric key is an invalid key.
     *
     * @param  array{votes: array<int, int>}  $input
     * @return array{votes: array<int, int>}
     */
    #[Query('catalog')]
    public function ratingByStars(array $input): array
    {
        return $input;
    }

    /**
     * An index-keyed tuple (array{0: ..., 1: ...}) with exact arity in both directions.
     *
     * @param  array{box: array{0: int, 1: string}}  $input
     * @return array{box: array{0: int, 1: string}}
     */
    #[Query('catalog')]
    public function dimensionsTuple(array $input): array
    {
        return $input;
    }

    /**
     * A tuple whose elements are a castable class, an enum and a generic type: hydrated objects
     * on the way in, plain JSON back out.
     *
     * @param  array{entry: array{Money, OrderStatus, DateTimeString<'Y-m-d'>}}  $input
     * @return array{entry: array{Money, OrderStatus, DateTimeString<'Y-m-d'>}}
     */
    #[Query('catalog')]
    public function mixedTuple(array $input): array
    {
        return $input;
    }

    /**
     * A nullable container: the union arm is the whole list, not its elements.
     *
     * @param  array{tags: list<non-empty-string>|null}  $input
     * @return array{tags: list<non-empty-string>|null}
     */
    #[Query('catalog')]
    public function maybeInventory(array $input): array
    {
        return $input;
    }

    /**
     * object{...} syntax with a quoted key: the handler receives and returns stdClass, and the
     * dashed key survives both directions.
     *
     * @param  object{"content-type": string, count: int}  $input
     * @return object{"content-type": string, count: int}
     */
    #[Query('catalog')]
    public function describeLabels(object $input): object
    {
        return $input;
    }

    /**
     * A root-level intersection of two shapes: parse merges the arms into one array, serialize
     * merges them into one object.
     *
     * @param  array{a: int}&array{b: string}  $input
     * @return array{a: int}&array{b: string}
     */
    #[Query('catalog')]
    public function searchFilters(array $input): array
    {
        return $input;
    }

    /**
     * A union nested inside a list: every element probes int first, then string.
     *
     * @param  array{values: list<int|string>}  $input
     * @return array{values: list<int|string>}
     */
    #[Query('catalog')]
    public function listOfUnions(array $input): array
    {
        return $input;
    }

    /**
     * A record of tuples: object values that are fixed-arity JSON arrays.
     *
     * @param  array{points: array<string, array{int, int}>}  $input
     * @return array{points: array<string, array{int, int}>}
     */
    #[Query('catalog')]
    public function tupleGrid(array $input): array
    {
        return $input;
    }

    /**
     * A discriminated union nested inside a list, so a bad element reports at events.N.
     *
     * @param  array{events: list<array{kind: 'restock', qty: int}|array{kind: 'sale', ref: string}>}  $input
     * @return array{kinds: list<string>, total: int}
     */
    #[Query('catalog')]
    public function feedEvents(array $input): array
    {
        return [
            'kinds' => array_column($input['events'], 'kind'),
            'total' => count($input['events']),
        ];
    }

    /**
     * A class-local alias (SkuList) next to a cross-file import renamed on arrival (Range).
     *
     * @param  array{range: Range, skus: SkuList}  $input
     * @return array{count: int, range: Range}
     */
    #[Query('catalog')]
    public function aliasedRange(array $input): array
    {
        return ['count' => count($input['skus']), 'range' => $input['range']];
    }

    /**
     * ApiToken exists nowhere in this codebase: it is a global alias registered on the custom
     * TypeParser the integration harness builds, resolving to non-empty-string.
     *
     * @param  array{token: ApiToken}  $input
     * @return array{token: ApiToken}
     */
    #[Query('catalog')]
    public function globalTokenEcho(array $input): array
    {
        return $input;
    }

    /**
     * An output-only generic container: no Castable attribute, so Paginated can never be an
     * input, and its two virtual getters are computed from the plain properties on the way out.
     *
     * @param  array{page: positive-int}  $input
     * @return Paginated<Sku>
     */
    #[Query('catalog')]
    public function pagedSkus(array $input): Paginated
    {
        return new Paginated(
            items: [Sku::fromStringValue('ABC-123'), Sku::fromStringValue('XYZ-999')],
            total: 5,
            currentPage: $input['page'],
            perPage: 2,
        );
    }
}
