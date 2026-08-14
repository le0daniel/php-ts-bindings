<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Collection and structural shapes: postfix arrays, non-empty and int-keyed records, keyed
 * tuples, nullable containers, object{} with quoted keys, intersections, and nested
 * combinations (unions in lists, records of tuples, discriminated unions in lists).
 */
test('postfix array syntax round-trips including a doubly nested int[][]', function () {
    expect(IntegrationHarness::queryJson('catalog.relatedSkus', '{"grid":[[1,2],[3]],"sku":"ABC-123"}'))
        ->toBe('{"success":true,"data":{"codes":["ABC-123"],"grid":[[1,2],[3]]}}');
});

test('an inner list rejects an assoc array at its indexed path', function () {
    expect(IntegrationHarness::queryJson('catalog.relatedSkus', '{"grid":[[1],{"a":2}],"sku":"ABC-123"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"grid.1":["validation.invalid_type"]}}}');
});

test('a list rejects an assoc array at the top level', function () {
    expect(IntegrationHarness::queryJson('catalog.relatedSkus', '{"grid":{"a":[1]},"sku":"ABC-123"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"grid":["validation.invalid_type"]}}}');
});

test('a non-empty record round-trips as a JSON object', function () {
    expect(IntegrationHarness::queryJson('catalog.priceBuckets', '{"thresholds":{"low":10,"high":90}}'))
        ->toBe('{"success":true,"data":{"thresholds":{"low":10,"high":90}}}');
});

test('a non-empty record rejects the empty object on parse', function () {
    expect(IntegrationHarness::queryJson('catalog.priceBuckets', '{"thresholds":{}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"thresholds":["validation.invalid_min"]}}}');
});

test('an int-keyed record accepts numeric JSON keys through PHP key folding', function () {
    expect(IntegrationHarness::queryJson('catalog.ratingByStars', '{"votes":{"1":10,"2":5}}'))
        ->toBe('{"success":true,"data":{"votes":{"1":10,"2":5}}}');
});

test('an int-keyed record rejects a non-numeric key at the key path', function () {
    expect(IntegrationHarness::queryJson('catalog.ratingByStars', '{"votes":{"abc":1}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"votes.abc":["validation.invalid_key_type"]}}}');
});

test('an index-keyed tuple round-trips as a JSON array', function () {
    expect(IntegrationHarness::queryJson('catalog.dimensionsTuple', '{"box":[10,"cm"]}'))
        ->toBe('{"success":true,"data":{"box":[10,"cm"]}}');
});

test('a tuple rejects too few elements', function () {
    expect(IntegrationHarness::queryJson('catalog.dimensionsTuple', '{"box":[10]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"box":["validation.invalid_type"]}}}');
});

test('a tuple rejects too many elements', function () {
    expect(IntegrationHarness::queryJson('catalog.dimensionsTuple', '{"box":[10,"cm",3]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"box":["validation.invalid_type"]}}}');
});

test('a tuple of castable, enum and DateTimeString round-trips element by element', function () {
    expect(IntegrationHarness::queryJson('catalog.mixedTuple', '{"entry":[{"amount":100,"currency":"chf"},"PAID","2024-06-01"]}'))
        ->toBe('{"success":true,"data":{"entry":[{"amount":100,"currency":"chf"},"PAID","2024-06-01"]}}');
});

test('a failing tuple element reports at its index', function () {
    expect(IntegrationHarness::queryJson('catalog.mixedTuple', '{"entry":[{"amount":100,"currency":"chf"},"UNKNOWN","2024-06-01"]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"entry.1":["validation.invalid_type"]}}}');
});

test('a nullable list accepts null and the list alike', function () {
    expect(IntegrationHarness::queryJson('catalog.maybeInventory', '{"tags":null}'))
        ->toBe('{"success":true,"data":{"tags":null}}');
    expect(IntegrationHarness::queryJson('catalog.maybeInventory', '{"tags":["a","b"]}'))
        ->toBe('{"success":true,"data":{"tags":["a","b"]}}');
});

test('a failing element inside a nullable list keeps its deep path next to the union issues', function () {
    expect(IntegrationHarness::queryJson('catalog.maybeInventory', '{"tags":["","b"]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"tags.0":["validation.not_empty_string"],"tags":["validation.invalid_type","validation.invalid_type"]}}}');
});

test('object{} syntax with a quoted key round-trips through stdClass', function () {
    expect(IntegrationHarness::queryJson('catalog.describeLabels', '{"content-type":"application/json","count":2}'))
        ->toBe('{"success":true,"data":{"content-type":"application\/json","count":2}}');
});

test('a root intersection merges both shapes in and out', function () {
    expect(IntegrationHarness::queryJson('catalog.searchFilters', '{"a":1,"b":"x"}'))
        ->toBe('{"success":true,"data":{"a":1,"b":"x"}}');
});

test('an intersection missing a property from one arm blames the enclosing root', function () {
    expect(IntegrationHarness::queryJson('catalog.searchFilters', '{"a":1}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"__root":["validation.missing_property"]}}}');
});

test('a union inside a list accepts both arms per element', function () {
    expect(IntegrationHarness::queryJson('catalog.listOfUnions', '{"values":[1,"two",3]}'))
        ->toBe('{"success":true,"data":{"values":[1,"two",3]}}');
});

test('a failing union element reports one issue per arm plus the union at its index', function () {
    expect(IntegrationHarness::queryJson('catalog.listOfUnions', '{"values":[true]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"values.0":["validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('a record of tuples round-trips object values as fixed-arity arrays', function () {
    expect(IntegrationHarness::queryJson('catalog.tupleGrid', '{"points":{"origin":[0,0],"corner":[4,2]}}'))
        ->toBe('{"success":true,"data":{"points":{"origin":[0,0],"corner":[4,2]}}}');
});

test('a failing tuple inside a record reports at its record key', function () {
    expect(IntegrationHarness::queryJson('catalog.tupleGrid', '{"points":{"corner":[1]}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"points.corner":["validation.invalid_type"]}}}');
});

test('a discriminated union inside a list resolves per element', function () {
    expect(IntegrationHarness::queryJson('catalog.feedEvents', '{"events":[{"kind":"restock","qty":5},{"kind":"sale","ref":"S-1"}]}'))
        ->toBe('{"success":true,"data":{"kinds":["restock","sale"],"total":2}}');
});

test('an unknown discriminator inside a list reports at the element index', function () {
    expect(IntegrationHarness::queryJson('catalog.feedEvents', '{"events":[{"kind":"restock","qty":5},{"kind":"noop"}]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"events.1":["validation.invalid_type"]}}}');
});
