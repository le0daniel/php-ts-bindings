<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Casting edge shapes end to end: property hooks with asymmetric visibility, unions of
 * castable classes, DateTime variants, generic containers, input-side projections, and all
 * three alias kinds (class-local, imported-with-rename, global).
 */
test('property hooks land per direction: raw in, checksum and summary out', function () {
    expect(IntegrationHarness::commandJson('shipping.registerHandling', '{"instructions":{"code":"frg","raw":"keep-cool"}}'))
        ->toBe('{"success":true,"data":{"checksum":"looc-peek","code":"frg","summary":"FRG"},"__metadata":{"key":"default1"}}');
});

test('a virtual set-only property is required on the input side', function () {
    expect(IntegrationHarness::commandJson('shipping.registerHandling', '{"instructions":{"code":"frg"}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"instructions":["validation.missing_property"]}},"__metadata":{"key":"default1"}}');
});

test('a union of castables resolves the first arm by its properties', function () {
    expect(IntegrationHarness::commandJson('shipping.scheduleDelivery', '{"destination":{"locationCode":"ZH-01"},"window":"01.07.2024 08:00"}'))
        ->toBe('{"success":true,"data":{"destination":{"locationCode":"ZH-01"},"eta":"2024-07-01T08:00:00.000+00:00","window":"01.07.2024 08:00"},"__metadata":{"key":"wow"}}');
});

test('a union of castables resolves the second arm by its properties', function () {
    expect(IntegrationHarness::commandJson('shipping.scheduleDelivery', '{"destination":{"street":"Seeweg 2","zip":"8001"},"window":"01.07.2024 08:00"}'))
        ->toBe('{"success":true,"data":{"destination":{"street":"Seeweg 2","zip":"8001"},"eta":"2024-07-01T08:00:00.000+00:00","window":"01.07.2024 08:00"},"__metadata":{"key":"wow"}}');
});

test('first-match probing wins on a shape satisfying both arms and drops unknown keys', function () {
    expect(IntegrationHarness::commandJson('shipping.scheduleDelivery', '{"destination":{"locationCode":"x","street":"y","zip":"z"},"window":"01.07.2024 08:00"}'))
        ->toBe('{"success":true,"data":{"destination":{"locationCode":"x"},"eta":"2024-07-01T08:00:00.000+00:00","window":"01.07.2024 08:00"},"__metadata":{"key":"wow"}}');
});

test('a shape matching neither castable arm reports every arm plus the union', function () {
    expect(IntegrationHarness::commandJson('shipping.scheduleDelivery', '{"destination":{"iban":"x"},"window":"01.07.2024 08:00"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"destination":["validation.missing_property","validation.missing_property","validation.invalid_type"]}},"__metadata":{"key":"wow"}}');
});

test('a DateTimeString with a custom format rejects the default format strictly', function () {
    expect(IntegrationHarness::commandJson('shipping.scheduleDelivery', '{"destination":{"locationCode":"ZH-01"},"window":"2024-07-01"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"window":["validation.invalid_type"]}},"__metadata":{"key":"wow"}}');
});

test('a generic castable binds a different type argument per direction', function () {
    expect(IntegrationHarness::commandJson('shipping.dispatchBatch', '{"count":2,"items":["ABC-123","XYZ-999"]}'))
        ->toBe('{"success":true,"data":{"count":2,"items":[{"amount":500,"currency":"chf"},{"amount":500,"currency":"chf"}]}}');
});

test('a generic type argument validates its elements at the indexed path', function () {
    expect(IntegrationHarness::commandJson('shipping.dispatchBatch', '{"count":1,"items":["bad"]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"items.0":["Sku must match ABC-123"]}}}');
});

test('an output-only paginated shape computes its virtual getters from the plain properties', function () {
    expect(IntegrationHarness::queryJson('catalog.pagedSkus', '{"page":1}'))
        ->toBe('{"success":true,"data":{"currentPage":1,"hasNextPage":true,"hasPreviousPage":false,"items":["ABC-123","XYZ-999"],"perPage":2,"total":5}}');
    expect(IntegrationHarness::queryJson('catalog.pagedSkus', '{"page":3}'))
        ->toBe('{"success":true,"data":{"currentPage":3,"hasNextPage":false,"hasPreviousPage":true,"items":["ABC-123","XYZ-999"],"perPage":2,"total":5}}');
});

test('the Named attribute has zero runtime effect on either registry', function () {
    expect(IntegrationHarness::commandJson('shipping.renameWarehouse', '{"warehouse":{"code":"ZH","region":"east"}}'))
        ->toBe('{"success":true,"data":{"code":"ZH","region":"east"}}');
});

test('Pick and Omit on the input side hydrate plain objects with only the projected keys', function () {
    expect(IntegrationHarness::commandJson(
        'shipping.updateManifest',
        '{"header":{"currency":"eur"},"partial":{"city":"Bern","street":"Marktgasse 4","zip":"3011"}}',
    ))->toBe('{"success":true,"data":{"city":"Bern","currency":"eur"}}');
});

test('a class-local alias and a renamed cross-file import resolve in one signature', function () {
    expect(IntegrationHarness::queryJson('catalog.aliasedRange', '{"range":{"max":9,"min":1},"skus":["ABC-123"]}'))
        ->toBe('{"success":true,"data":{"count":1,"range":{"max":9,"min":1}}}');
});

test('an aliased non-empty list still enforces its constraint', function () {
    expect(IntegrationHarness::queryJson('catalog.aliasedRange', '{"range":{"max":9,"min":1},"skus":[]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"skus":["validation.invalid_min"]}}}');
});

test('a global alias registered on the parser resolves like any other type', function () {
    expect(IntegrationHarness::queryJson('catalog.globalTokenEcho', '{"token":"tok-1"}'))
        ->toBe('{"success":true,"data":{"token":"tok-1"}}');
});

test('a global alias keeps the refinement it resolves to', function () {
    expect(IntegrationHarness::queryJson('catalog.globalTokenEcho', '{"token":""}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"token":["validation.not_empty_string"]}}}');
});

test('a branded string inside a non-empty list stays a plain string with the list constraint', function () {
    expect(IntegrationHarness::commandJson('shipping.tagShipment', '{"tags":["fragile"]}'))
        ->toBe('{"success":true,"data":{"tags":["fragile"]}}');
    expect(IntegrationHarness::commandJson('shipping.tagShipment', '{"tags":[]}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"tags":["validation.invalid_min"]}}}');
});
