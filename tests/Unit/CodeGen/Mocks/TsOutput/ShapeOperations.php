<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput;

use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\Availability;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\Product;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\ProductId;

/**
 * One operation per structural corner of the type table: everything the parser can express has to
 * come out as TypeScript the compiler accepts.
 */
final class ShapeOperations
{
    /**
     * A query without input. Every generator that emits a signature for it has to drop the
     * argument, which is a different code path in all three of them.
     *
     * @return array{
     *     text: string,
     *     count: int,
     *     ratio: float,
     *     enabled: bool,
     *     nothing: null,
     *     anything: mixed,
     *     literal: 'fixed',
     *     answer: 42,
     *     always: true,
     *     tags: string[],
     *     lookup: array<string, int>,
     *     byId: array<int, Product>,
     *     modes: array<'draft'|'live', int>,
     *     pair: array{string, int},
     *     either: string|int,
     *     maybe: ?Availability,
     *     createdAt: DateTimeImmutable,
     *     day: DateTimeString<'Y-m-d'>,
     *     nested: array{deep: array{value: non-empty-string}},
     *     products: list<Product>,
     * }
     */
    #[Query('shapes')]
    public function defaults(null $input): array
    {
        return [
            'text' => '',
            'count' => 0,
            'ratio' => 0.0,
            'enabled' => false,
            'nothing' => null,
            'anything' => null,
            'literal' => 'fixed',
            'answer' => 42,
            'always' => true,
            'tags' => [],
            'lookup' => [],
            'byId' => [],
            'modes' => [],
            'pair' => ['', 0],
            'either' => 0,
            'maybe' => null,
            'createdAt' => new DateTimeImmutable(),
            'day' => new DateTimeImmutable(),
            'nested' => ['deep' => ['value' => 'a']],
            'products' => [],
        ];
    }

    /**
     * Optional keys on the way in and out, so the generated types carry `?:` in both directions.
     *
     * @param  array{term: non-empty-string, page?: positive-int, filters: array<string, list<string>>}  $input
     * @return array{term: string, page?: int, filters: array<string, list<string>>}
     */
    #[Query('shapes')]
    public function roundtrip(array $input): array
    {
        return ['term' => $input['term'], 'filters' => $input['filters']];
    }

    /**
     * @param  array{payload: array{id: ProductId, when: DateTimeString<'Y-m-d'>}, dryRun?: bool}  $input
     * @return array{accepted: bool, id: ProductId}
     */
    #[Command('shapes')]
    public function submit(array $input): array
    {
        return ['accepted' => true, 'id' => $input['payload']['id']];
    }

    /**
     * The docblock metadata utilities: Named exports an alias, Branded intersects a Brand and
     * declares the implicit alias, and an inner Named renames the outer Branded.
     *
     * @return array{
     *     token: Branded<'sessionToken', non-empty-string>,
     *     ref: Branded<'shapeRef', Named<'ShapeRef', string>>,
     *     origin: Named<'Origin', array{lat: float, lng: float}>,
     * }
     */
    #[Query('shapes')]
    public function metadataUtilities(null $input): array
    {
        return [
            'token' => 'tok-1',
            'ref' => 'REF-9',
            'origin' => ['lat' => 0.0, 'lng' => 0.0],
        ];
    }
}
