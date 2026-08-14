<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Execution modes and edge interplay: query-only primitive coercion for bool and float at any
 * depth, the no-partial-failure serialization policy, deep error paths through castables, and
 * the optional/nullable distinction.
 */
test('a bool query param is coerced from "true" and "0" when coercion is enabled', function () {
    expect(IntegrationHarness::queryJson('inventory.stockFlag', '{"inStock":"true"}', coerceQueryInput: true))
        ->toBe('{"success":true,"data":{"inStock":true}}');
    expect(IntegrationHarness::queryJson('inventory.stockFlag', '{"inStock":"0"}', coerceQueryInput: true))
        ->toBe('{"success":true,"data":{"inStock":false}}');
});

test('the same bool string is rejected when coercion is disabled', function () {
    expect(IntegrationHarness::queryJson('inventory.stockFlag', '{"inStock":"true"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"inStock":["validation.invalid_type"]}}}');
});

test('a float query param is coerced from its string form when coercion is enabled', function () {
    expect(IntegrationHarness::queryJson('inventory.convertWeight', '"2.5"', coerceQueryInput: true))
        ->toBe('{"success":true,"data":2.5}');
});

test('coercion applies per leaf inside nested structs: int, float and bool at once', function () {
    expect(IntegrationHarness::queryJson(
        'inventory.warehouseCapacity',
        '{"filters":{"includeEmpty":"1","limit":"5","ratio":"1.5"}}',
        coerceQueryInput: true,
    ))->toBe('{"success":true,"data":{"includeEmpty":true,"limit":5,"ratio":1.5}}');
});

test('without coercion the struct fails fast on its first invalid property in canonical order', function () {
    expect(IntegrationHarness::queryJson('inventory.warehouseCapacity', '{"filters":{"includeEmpty":"1","limit":"5","ratio":"1.5"}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"filters.includeEmpty":["validation.invalid_type"]}}}');
});

test('commands never coerce, even for input a query would accept', function () {
    expect(IntegrationHarness::commandJson('cart.applyVoucher', '{"code":"SUMMER","percent":"15"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"percent":["validation.invalid_type"]}}}');
});

test('a nullable DateTimeString output serializes null and a real date', function () {
    expect(IntegrationHarness::commandJson('shipping.holdShipment', '{"orderNumber":"ORD-1"}'))
        ->toBe('{"success":true,"data":{"until":null}}');
    expect(IntegrationHarness::commandJson('shipping.holdShipment', '{"orderNumber":"ORD-HOLD"}'))
        ->toBe('{"success":true,"data":{"until":"2024-09-15"}}');
});

test('an output violating a nullable union answers 500 and never degrades to null', function () {
    expect(IntegrationHarness::commandJson('shipping.holdShipment', '{"orderNumber":"ORD-BAD"}'))
        ->toBe('{"success":false,"code":500,"type":"INTERNAL_ERROR"}');
});

test('a failing leaf nested in list and castable reports its full dotted path', function () {
    expect(IntegrationHarness::commandJson(
        'shipping.estimateCost',
        '{"shipments":[{"address":{"city":"Bern","street":"Marktgasse 4","zip":123},"ref":"R-1"}]}',
    ))->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"shipments.0.address.zip":["validation.invalid_type"]}}}');
});

test('an optional nullable key distinguishes absent, null and present', function () {
    expect(IntegrationHarness::commandJson('shipping.applyCredit', '{"reference":null}'))
        ->toBe('{"success":true,"data":{"hadNote":false,"note":null,"reference":null}}');
    expect(IntegrationHarness::commandJson('shipping.applyCredit', '{"note":null,"reference":"ORD-77"}'))
        ->toBe('{"success":true,"data":{"hadNote":true,"note":null,"reference":"ORD-77"}}');
});

test('a value-object-or-null union reports the verbatim rejection next to the arm issues', function () {
    expect(IntegrationHarness::commandJson('shipping.applyCredit', '{"reference":"1001"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"reference":["validation.invalid_value","validation.invalid_type","validation.invalid_type"]}}}');
});

test('the ?T prefix sugar behaves exactly like the spelled-out null union', function () {
    expect(IntegrationHarness::commandJson('shipping.annotateShipment', '{"legacyNote":null,"modernNote":"kept"}'))
        ->toBe('{"success":true,"data":{"legacyNote":null,"modernNote":"kept"}}');
    expect(IntegrationHarness::commandJson('shipping.annotateShipment', '{"legacyNote":5,"modernNote":"kept"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"legacyNote":["validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('optional keys with complex values fall back to handler defaults when absent', function () {
    expect(IntegrationHarness::commandJson('shipping.optionalExtras', '{}'))
        ->toBe('{"success":true,"data":{"hasFallback":false,"priority":2}}');
    expect(IntegrationHarness::commandJson(
        'shipping.optionalExtras',
        '{"fallbackAddress":{"city":"Bern","street":"Marktgasse 4","zip":"3011"},"priority":1}',
    ))->toBe('{"success":true,"data":{"hasFallback":true,"priority":1}}');
});

test('a provided optional literal union still validates its arms', function () {
    expect(IntegrationHarness::commandJson('shipping.optionalExtras', '{"priority":4}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"priority":["validation.invalid_type","validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});
