<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Integration\Fixtures\Types\PalletSize;
use Tests\Integration\Fixtures\Types\ShippingClass;
use Tests\Integration\Fixtures\Types\StockLevel;
use Tests\Integration\Fixtures\Types\WarehouseId;

/**
 * Scalar leaves, literal kinds and int-flavoured enums/value objects. Every handler is a pure
 * function of its input so the end-to-end tests can assert the exact envelope JSON.
 */
final class InventoryQueries
{
    /**
     * A bare float as both input and output: parse accepts ints unchanged, serialize casts to
     * float, so an int 3 comes back as 3.0 on the wire.
     *
     * @param  float  $input
     * @return float
     */
    #[Query('inventory')]
    public function convertWeight(float|int $input): float|int
    {
        return $input;
    }

    /**
     * A bool on the INPUT side, and the coercion target for "true"/"0" query strings.
     *
     * @param  array{inStock: bool}  $input
     * @return array{inStock: bool}
     */
    #[Query('inventory')]
    public function stockFlag(array $input): array
    {
        return $input;
    }

    /**
     * mixed passes any JSON through untouched in both directions.
     *
     * @param  array{meta: mixed}  $input
     * @return array{meta: mixed}
     */
    #[Query('inventory')]
    public function echoMetadata(array $input): array
    {
        return $input;
    }

    /**
     * The scalar shorthand expands to int|float|bool|string, so a failure reports one issue per
     * arm plus one for the union itself.
     *
     * @param  array{value: scalar}  $input
     * @return array{value: scalar}
     */
    #[Query('inventory')]
    public function normalizeCode(array $input): array
    {
        return $input;
    }

    /**
     * The numeric shorthand expands to int|float; a numeric STRING is not numeric.
     *
     * @param  array{a: numeric, b: numeric}  $input
     * @return array{total: numeric}
     */
    #[Query('inventory')]
    public function sumNumeric(array $input): array
    {
        return ['total' => $input['a'] + $input['b']];
    }

    /**
     * One struct covering the literal kinds the other fixtures never touch: float literals, the
     * false literal, a bare null member, and class-constant literals resolved at parse time.
     *
     * @param  array{factor: 0.5|1.5, flag: false, legacy: null, mode: ShippingClass::EXPRESS|ShippingClass::STANDARD}  $input
     * @return array{factor: 0.5|1.5, flag: false, legacy: null, mode: ShippingClass::EXPRESS|ShippingClass::STANDARD}
     */
    #[Query('inventory')]
    public function literalSampler(array $input): array
    {
        return $input;
    }

    /**
     * int, float and bool side by side inside a nested struct: the target for proving query
     * coercion applies per-leaf at any depth, not only at the top level.
     *
     * @param  array{filters: array{includeEmpty: bool, limit: int, ratio: float}}  $input
     * @return array{includeEmpty: bool, limit: int, ratio: float}
     */
    #[Query('inventory')]
    public function warehouseCapacity(array $input): array
    {
        return $input['filters'];
    }

    /**
     * A branded IntValueObject: plain number on the wire both ways, ValidationException message
     * verbatim on rejection.
     *
     * @param  array{id: WarehouseId}  $input
     * @return array{id: WarehouseId, name: string}
     */
    #[Query('inventory')]
    public function lookupWarehouse(array $input): array
    {
        return ['id' => $input['id'], 'name' => 'Zurich Hub'];
    }

    /**
     * The int contrast pair: StockLevel is backed but NOT a value object (case names on the
     * wire), PalletSize opts into IntValueObject (backing ints on the wire).
     *
     * @param  array{level: StockLevel, size: PalletSize}  $input
     * @return array{level: StockLevel, size: PalletSize}
     */
    #[Query('inventory')]
    public function palletReport(array $input): array
    {
        return $input;
    }
}
