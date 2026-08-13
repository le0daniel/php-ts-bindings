<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * The error catalogue end to end: domain errors, Throws-typed mappings, output violations,
 * routing misses and the exact 422 field paths.
 */
test('cancelOrder succeeds with a literal true in the output', function () {
    expect(IntegrationHarness::commandJson('orders.cancelOrder', '{"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":true,"data":{"cancelled":true,"orderNumber":"ORD-1001"}}');
});

test('a declared domain exception maps to DOMAIN_ERROR with its registered name', function () {
    expect(IntegrationHarness::commandJson('orders.cancelOrder', '{"orderNumber":"ORD-SHIPPED"}'))
        ->toBe('{"success":false,"code":400,"type":"DOMAIN_ERROR","details":{"name":"order_already_shipped"}}');
});

test('requestRefund round-trips Money through input and output', function () {
    expect(IntegrationHarness::commandJson('orders.requestRefund', '{"amount":{"amount":500,"currency":"eur"},"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":true,"data":{"refund":{"amount":500,"currency":"eur"},"status":"refunded"}}');
});

test('a Throws-typed exception maps to NOT_FOUND without details', function () {
    expect(IntegrationHarness::commandJson('orders.requestRefund', '{"amount":{"amount":500,"currency":"eur"},"orderNumber":"ORD-MISSING"}'))
        ->toBe('{"success":false,"code":404,"type":"NOT_FOUND"}');
});

test('output violating the declared type is an INTERNAL_ERROR without details', function () {
    expect(IntegrationHarness::commandJson('orders.recordPaymentWebhook', '{"payload":"evt_1"}'))
        ->toBe('{"success":false,"code":500,"type":"INTERNAL_ERROR"}');
});

test('an unknown operation key is NOT_FOUND', function () {
    expect(IntegrationHarness::commandJson('orders.doesNotExist', '{}'))
        ->toBe('{"success":false,"code":404,"type":"NOT_FOUND"}');
});

test('a query key is not reachable as a command', function () {
    expect(IntegrationHarness::commandJson('orders.getOrder', '{"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":false,"code":404,"type":"NOT_FOUND"}');
});

test('a command key is not reachable as a query', function () {
    expect(IntegrationHarness::queryJson('orders.archive', '{"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":false,"code":404,"type":"NOT_FOUND"}');
});

test('a rejection inside a list of castables reports the dot-joined path', function () {
    expect(IntegrationHarness::commandJson(
        'orders.placeOrder',
        '{"currency":"chf","items":[{"sku":"bad","quantity":2}],"shippingAddress":{"city":"a","street":"x","zip":"1"}}',
    ))->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"items.0.sku":["Sku must match ABC-123"]}}}');
});

test('a missing nested property is reported at the enclosing struct path', function () {
    // The parser fails fast and attributes a missing key to the struct that lacks it, not to the
    // missing key's own path - the parse-side counterpart pinned at the top level by
    // LaravelHttpControllerTest's __root assertion.
    expect(IntegrationHarness::commandJson(
        'orders.placeOrder',
        '{"currency":"chf","items":[{"sku":"ABC-123","quantity":2}],"shippingAddress":{"street":"x","zip":"1"}}',
    ))->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"shippingAddress":["validation.missing_property"]}}}');
});

test('an empty non-empty-list is rejected with invalid_min', function () {
    expect(IntegrationHarness::commandJson(
        'orders.placeOrder',
        '{"currency":"chf","items":[],"shippingAddress":{"city":"a","street":"x","zip":"1"}}',
    ))->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"items":["validation.invalid_min"]}}}');
});

test('null input for a required struct is reported at the root path', function () {
    expect(IntegrationHarness::commandJson('orders.placeOrder'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"__root":["validation.invalid_type"]}}}');
});

test('a bounded int refinement rejects below the minimum on parse', function () {
    expect(IntegrationHarness::commandJson('cart.applyVoucher', '{"code":"SUMMER","percent":0}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"percent":["validation.invalid_min"]}}}');
});

test('the same refinement passes within bounds', function () {
    expect(IntegrationHarness::commandJson('cart.applyVoucher', '{"code":"SUMMER","percent":15}'))
        ->toBe('{"success":true,"data":{"applied":true,"discount":{"amount":150,"currency":"chf"}}}');
});

test('a malformed date string is rejected by the strict DateTimeString format', function () {
    expect(IntegrationHarness::commandJson('cart.setDeliveryDate', '{"date":"01.06.2024"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"date":["validation.invalid_type"]}}}');
});
