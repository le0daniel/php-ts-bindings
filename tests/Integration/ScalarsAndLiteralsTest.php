<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Scalar leaves and literal kinds end to end: float, bool on the input side, mixed, the
 * scalar/numeric shorthands, float/false/null/class-const literals, and the int-flavoured
 * enum and value object variants. Every call runs against the eager registry AND the
 * file-cached registry (see IntegrationHarness).
 */
test('a bare float round-trips as the envelope data', function () {
    expect(IntegrationHarness::queryJson('inventory.convertWeight', '2.5'))
        ->toBe('{"success":true,"data":2.5}');
});

test('an int passes a float schema and stays a plain number on the wire', function () {
    expect(IntegrationHarness::queryJson('inventory.convertWeight', '3'))
        ->toBe('{"success":true,"data":3}');
});

test('a float rejects a numeric string without coercion at the root path', function () {
    expect(IntegrationHarness::queryJson('inventory.convertWeight', '"2.5"'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"__root":["validation.invalid_type"]}}}');
});

test('a bool input echoes both cases', function () {
    expect(IntegrationHarness::queryJson('inventory.stockFlag', '{"inStock":true}'))
        ->toBe('{"success":true,"data":{"inStock":true}}');
    expect(IntegrationHarness::queryJson('inventory.stockFlag', '{"inStock":false}'))
        ->toBe('{"success":true,"data":{"inStock":false}}');
});

test('a bool rejects a non-boolean string', function () {
    expect(IntegrationHarness::queryJson('inventory.stockFlag', '{"inStock":"yes"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"inStock":["validation.invalid_type"]}}}');
});

test('mixed passes arbitrary nested JSON through untouched in both directions', function () {
    expect(IntegrationHarness::queryJson('inventory.echoMetadata', '{"meta":{"nested":[1,"a",null]}}'))
        ->toBe('{"success":true,"data":{"meta":{"nested":[1,"a",null]}}}');
});

test('the scalar shorthand accepts every scalar arm', function () {
    expect(IntegrationHarness::queryJson('inventory.normalizeCode', '{"value":"txt"}'))
        ->toBe('{"success":true,"data":{"value":"txt"}}');
    expect(IntegrationHarness::queryJson('inventory.normalizeCode', '{"value":7}'))
        ->toBe('{"success":true,"data":{"value":7}}');
});

test('the scalar shorthand rejects an object with one issue per arm plus the union', function () {
    expect(IntegrationHarness::queryJson('inventory.normalizeCode', '{"value":{}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"value":["validation.invalid_type","validation.invalid_type","validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('the numeric shorthand accepts int and float together', function () {
    expect(IntegrationHarness::queryJson('inventory.sumNumeric', '{"a":1,"b":2.5}'))
        ->toBe('{"success":true,"data":{"total":3.5}}');
});

test('the numeric shorthand rejects a numeric string', function () {
    expect(IntegrationHarness::queryJson('inventory.sumNumeric', '{"a":"1","b":2}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"a":["validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('float, false, null and class-const literals echo through both directions', function () {
    expect(IntegrationHarness::queryJson('inventory.literalSampler', '{"factor":0.5,"flag":false,"legacy":null,"mode":"express"}'))
        ->toBe('{"success":true,"data":{"factor":0.5,"flag":false,"legacy":null,"mode":"express"}}');
});

test('a float literal union rejects a float outside the set', function () {
    expect(IntegrationHarness::queryJson('inventory.literalSampler', '{"factor":2.5,"flag":false,"legacy":null,"mode":"express"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"factor":["validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('the false literal rejects true', function () {
    expect(IntegrationHarness::queryJson('inventory.literalSampler', '{"factor":0.5,"flag":true,"legacy":null,"mode":"express"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"flag":["validation.invalid_type"]}}}');
});

test('a null struct member rejects a non-null value', function () {
    expect(IntegrationHarness::queryJson('inventory.literalSampler', '{"factor":0.5,"flag":false,"legacy":"x","mode":"express"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"legacy":["validation.invalid_type"]}}}');
});

test('a class-const literal union rejects a value outside the constant set', function () {
    expect(IntegrationHarness::queryJson('inventory.literalSampler', '{"factor":0.5,"flag":false,"legacy":null,"mode":"overnight"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"mode":["validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('a branded IntValueObject is a plain number on the wire in both directions', function () {
    expect(IntegrationHarness::queryJson('inventory.lookupWarehouse', '{"id":7}'))
        ->toBe('{"success":true,"data":{"id":7,"name":"Zurich Hub"}}');
});

test('an IntValueObject rejecting with ValidationException surfaces its message verbatim', function () {
    expect(IntegrationHarness::queryJson('inventory.lookupWarehouse', '{"id":0}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"id":["Warehouse id must be positive"]}}}');
});

test('an int-backed enum serializes by case name while a value-object enum uses its backing int', function () {
    expect(IntegrationHarness::queryJson('inventory.palletReport', '{"level":"LOW","size":2}'))
        ->toBe('{"success":true,"data":{"level":"LOW","size":2}}');
});

test('a value-object enum collapses an unknown backing int to the generic invalid_value key', function () {
    expect(IntegrationHarness::queryJson('inventory.palletReport', '{"level":"LOW","size":9}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"size":["validation.invalid_value"]}}}');
});

test('a plain int-backed enum rejects its backing int because the wire form is the case name', function () {
    expect(IntegrationHarness::queryJson('inventory.palletReport', '{"level":1,"size":2}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"level":["validation.invalid_type"]}}}');
});
