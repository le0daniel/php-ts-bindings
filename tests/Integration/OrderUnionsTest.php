<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Union behavior end to end: discriminated unions in both directions, undiscriminated
 * first-match probing, and literal/enum-case unions.
 */
test('trackingEvent serializes the created branch of a discriminated union', function () {
    expect(IntegrationHarness::queryJson('orders.trackingEvent', '{"stage":"created"}'))
        ->toBe('{"success":true,"data":{"at":"2024-05-01","kind":"created"}}');
});

test('trackingEvent serializes the shipped branch of a discriminated union', function () {
    expect(IntegrationHarness::queryJson('orders.trackingEvent', '{"stage":"shipped"}'))
        ->toBe('{"success":true,"data":{"carrier":"DHL","kind":"shipped","trackingCode":"JJD-0003-9000-7882"}}');
});

test('trackingEvent serializes the delivered branch including an explicit null', function () {
    expect(IntegrationHarness::queryJson('orders.trackingEvent', '{"stage":"delivered"}'))
        ->toBe('{"success":true,"data":{"kind":"delivered","signedBy":null}}');
});

test('payOrder parses the card branch of a discriminated input union', function () {
    expect(IntegrationHarness::commandJson('checkout.payOrder', '{"kind":"card","cardNumber":"4242424242424242"}'))
        ->toBe('{"success":true,"data":{"method":"CARD","reference":"pay-card-4242"}}');
});

test('payOrder parses the invoice branch of a discriminated input union', function () {
    expect(IntegrationHarness::commandJson('checkout.payOrder', '{"kind":"invoice","iban":"CH9300762011623852957"}'))
        ->toBe('{"success":true,"data":{"method":"INVOICE","reference":"pay-invoice-2957"}}');
});

test('payOrder parses the twint branch and serializes the backed enum by case name', function () {
    expect(IntegrationHarness::commandJson('checkout.payOrder', '{"kind":"twint","phone":"+41791234567"}'))
        ->toBe('{"success":true,"data":{"method":"TWINT","reference":"pay-twint-567"}}');
});

test('payOrder rejects an unknown discriminator value with a root-level 422', function () {
    expect(IntegrationHarness::commandJson('checkout.payOrder', '{"kind":"cash"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"__root":["validation.invalid_type"]}}}');
});

test('searchOrders accepts the string branch of an undiscriminated union', function () {
    expect(IntegrationHarness::queryJson('orders.searchOrders', '{"filter":"ada"}'))
        ->toBe('{"success":true,"data":["ORD-1001","ORD-1003"]}');
});

test('searchOrders accepts the struct branch of an undiscriminated union', function () {
    expect(IntegrationHarness::queryJson('orders.searchOrders', '{"filter":{"status":"PENDING"}}'))
        ->toBe('{"success":true,"data":["ORD-BY-STATUS-PENDING"]}');
});

test('flagPriority accepts int literals and enum-case literals', function () {
    expect(IntegrationHarness::commandJson('checkout.flagPriority', '{"level":1,"status":"PAID"}'))
        ->toBe('{"success":true,"data":{"flagged":"high","level":1}}');
});

test('flagPriority rejects an int outside the literal union, one issue per failed arm plus the union itself', function () {
    expect(IntegrationHarness::commandJson('checkout.flagPriority', '{"level":4,"status":"PAID"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"level":["validation.invalid_type","validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});

test('flagPriority rejects an enum case outside the declared case-literal union', function () {
    expect(IntegrationHarness::commandJson('checkout.flagPriority', '{"level":1,"status":"SHIPPED"}'))
        ->toBe('{"success":false,"code":422,"type":"INVALID_INPUT","details":{"fields":{"status":["validation.invalid_type","validation.invalid_type","validation.invalid_type"]}}}');
});
