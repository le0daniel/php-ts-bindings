<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Casting end to end: castable classes in both strategies, value objects with their rejection
 * modes, Optional defaults, and query-only primitive coercion.
 */
test('placeOrder hydrates nested castables and value objects into computed output', function () {
    $json = '{"currency":"chf","items":[{"sku":"ABC-123","quantity":2},{"sku":"XYZ-999","quantity":1,"note":"gift"}],'
        .'"shippingAddress":{"city":"Zurich","street":"Bahnhofstrasse 1","zip":"8001"}}';

    expect(IntegrationHarness::commandJson('orders.placeOrder', $json))->toBe(json_encode([
        'success' => true,
        'data' => [
            'itemCount' => 2,
            'orderNumber' => 'ORD-NEW-1',
            'status' => 'PENDING',
            'total' => ['amount' => 750, 'currency' => 'chf'],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('updateShippingAddress defaults the Optional company to null when omitted', function () {
    expect(IntegrationHarness::commandJson(
        'orders.updateShippingAddress',
        '{"address":{"city":"Bern","street":"Marktgasse 5","zip":"3011"},"orderNumber":"ORD-1001"}',
    ))->toBe('{"success":true,"data":{"city":"Bern","company":null,"street":"Marktgasse 5","zip":"3011"}}');
});

test('updateShippingAddress echoes the Optional company when provided', function () {
    expect(IntegrationHarness::commandJson(
        'orders.updateShippingAddress',
        '{"address":{"city":"Bern","company":"ACME AG","street":"Marktgasse 5","zip":"3011"},"orderNumber":"ORD-1001"}',
    ))->toBe('{"success":true,"data":{"city":"Bern","company":"ACME AG","street":"Marktgasse 5","zip":"3011"}}');
});

test('addItem defaults the Optional constructor param to null when omitted', function () {
    expect(IntegrationHarness::commandJson('cart.addItem', '{"item":{"sku":"ABC-123","quantity":2}}'))
        ->toBe('{"success":true,"data":{"count":1,"items":[{"note":null,"quantity":2,"sku":"ABC-123"}]}}');
});

test('addItem passes the Optional constructor param through when provided', function () {
    expect(IntegrationHarness::commandJson('cart.addItem', '{"item":{"sku":"ABC-123","quantity":2,"note":"engrave"}}'))
        ->toBe('{"success":true,"data":{"count":1,"items":[{"note":"engrave","quantity":2,"sku":"ABC-123"}]}}');
});

test('listOrders applies handler defaults for omitted optional keys', function () {
    expect(IntegrationHarness::queryJson('orders.listOrders', '{}'))
        ->toBe('{"success":true,"data":{"orders":[{"orderNumber":"ORD-1001","status":"PAID"},{"orderNumber":"ORD-1002","status":"PENDING"}],"page":1,"perPage":20}}');
});

test('a string query param is coerced to int when coercion is enabled', function () {
    expect(IntegrationHarness::queryJson('orders.listOrders', '{"page":"2"}', coerceQueryInput: true))
        ->toBe('{"success":true,"data":{"orders":[{"orderNumber":"ORD-1001","status":"PAID"},{"orderNumber":"ORD-1002","status":"PENDING"}],"page":2,"perPage":20}}');
});

test('the same string query param is rejected when coercion is disabled', function () {
    expect(IntegrationHarness::queryJson('orders.listOrders', '{"page":"2"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"page":["validation.invalid_type"]}}}');
});

test('a value object rejecting with ValidationException surfaces its message verbatim', function () {
    expect(IntegrationHarness::queryJson('orders.parcelDimensions', '{"sku":"nope"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"sku":["Sku must match ABC-123"]}}}');
});

test('an int value object rejects below its minimum at the nested path', function () {
    expect(IntegrationHarness::commandJson('cart.addItem', '{"item":{"sku":"ABC-123","quantity":0}}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"item.quantity":["Quantity must be at least 1"]}}}');
});

test('a plain throwable in a value object collapses to the generic invalid_value key', function () {
    expect(IntegrationHarness::queryJson('orders.invoiceFileName', '{"orderNumber":"1001"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"orderNumber":["validation.invalid_value"]}}}');
});
