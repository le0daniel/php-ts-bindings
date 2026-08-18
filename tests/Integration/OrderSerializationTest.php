<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * End-to-end serialization shapes: JSON string in, exact envelope JSON string out. Every call
 * runs against the eager registry AND the file-cached registry (see IntegrationHarness).
 */
test('getOrder serializes the full nested order exactly', function () {
    expect(IntegrationHarness::queryJson('orders.getOrder', '{"orderNumber":"ORD-1001"}'))->toBe(json_encode([
        'success' => true,
        'data' => [
            'createdAt' => '2024-05-01T12:00:00.000+00:00',
            'currency' => 'chf',
            'items' => [
                ['lineTotal' => ['amount' => 1000, 'currency' => 'chf'], 'quantity' => 2, 'sku' => 'ABC-123'],
                ['lineTotal' => ['amount' => 1495, 'currency' => 'chf'], 'quantity' => 1, 'sku' => 'XYZ-999'],
            ],
            'shippingAddress' => ['city' => 'Zurich', 'company' => null, 'street' => 'Bahnhofstrasse 1', 'zip' => '8001'],
            'status' => 'PAID',
            'total' => ['amount' => 2495, 'currency' => 'chf'],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('statusCounts emits a closed record and an empty record as an object', function () {
    expect(IntegrationHarness::queryJson('orders.statusCounts'))
        ->toBe('{"success":true,"data":{"counts":{"PAID":2,"PENDING":1,"SHIPPED":0},"emptyByDay":{}}}');
});

test('orderSummary serializes an output-only class', function () {
    expect(IntegrationHarness::queryJson('orders.orderSummary', '{"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":true,"data":{"itemCount":3,"orderNumber":"ORD-1001","status":"SHIPPED","total":{"amount":2495,"currency":"chf"}}}');
});

test('parcelDimensions returns an exact-arity tuple and round-trips the sku', function () {
    expect(IntegrationHarness::queryJson('orders.parcelDimensions', '{"sku":"ABC-123"}'))
        ->toBe('{"success":true,"data":{"dimensionsMm":[300,200,50],"sku":"ABC-123"}}');
});

test('sessionRefs returns branded types as plain string and int', function () {
    expect(IntegrationHarness::queryJson('orders.sessionRefs'))
        ->toBe('{"success":true,"data":{"customerId":512,"orderId":"ORD-1001"}}');
});

test('invoiceFileName returns a bare scalar as the envelope data', function () {
    expect(IntegrationHarness::queryJson('orders.invoiceFileName', '{"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":true,"data":"invoice-ORD-1001.pdf"}');
});

test('customerSnapshot serializes Pick and Omit projections of a full instance', function () {
    expect(IntegrationHarness::queryJson('orders.customerSnapshot'))
        ->toBe('{"success":true,"data":{"card":{"email":"ada@example.com","name":"Ada"},"publicCard":{"email":"ada@example.com","name":"Ada","tier":"gold"}}}');
});

test('setDeliveryDate round-trips a Y-m-d date and derives a tuple window', function () {
    expect(IntegrationHarness::commandJson('cart.setDeliveryDate', '{"date":"2024-06-01"}'))
        ->toBe('{"success":true,"data":{"confirmed":"2024-06-01","window":["2024-06-01","2024-06-03"]}}');
});

test('the archive command is reachable under its overridden name only', function () {
    expect(IntegrationHarness::commandJson('orders.archive', '{"orderNumber":"ORD-1001"}'))
        ->toBe('{"success":true,"data":{"archived":true,"orderNumber":"ORD-1001"}}');
});

test('bulkUpdateStatus echoes a record of enums by case name', function () {
    expect(IntegrationHarness::commandJson('orders.bulkUpdateStatus', '{"ORD-1001":"SHIPPED","ORD-1002":"CANCELLED"}'))
        ->toBe('{"success":true,"data":{"updated":{"ORD-1001":"SHIPPED","ORD-1002":"CANCELLED"}}}');
});

test('an empty record input stays an empty object and never degrades to an array', function () {
    expect(IntegrationHarness::commandJson('orders.bulkUpdateStatus', '{}'))
        ->toBe('{"success":true,"data":{"updated":{}}}');
});
