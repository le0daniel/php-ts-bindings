<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Every string and int refinement, each pinned to its exact issue key at its exact path.
 * Refinements run on parse only; a valid payload is the shared baseline for all failure cases.
 */
const QUALITY_GATE_VALID = [
    'amount' => '12.5',
    'code' => 'x',
    'comment' => 'ok',
    'label' => 'UP',
    'memo' => 'y',
    'slug' => 'low',
    'tag' => 'tag',
    'ticker' => 'TCK',
];

const BOUNDS_CHECK_VALID = [
    'debt' => 0,
    'delta' => -3,
    'drop' => -1,
    'floor' => 0,
    'growth' => 1,
    'level' => 0,
];

function qualityGateWith(string $key, string $value): string
{
    return json_encode([...QUALITY_GATE_VALID, $key => $value], JSON_THROW_ON_ERROR);
}

function boundsCheckWith(string $key, int $value): string
{
    return json_encode([...BOUNDS_CHECK_VALID, $key => $value], JSON_THROW_ON_ERROR);
}

function refinementFailure(string $field, string $issueKey): string
{
    return json_encode([
        'success' => false,
        'code' => 422,
        'type' => 'INVALID_INPUT',
        'details' => ['fields' => [$field => [$issueKey]]],
    ], JSON_THROW_ON_ERROR);
}

test('all eight string refinements pass together', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', json_encode(QUALITY_GATE_VALID, JSON_THROW_ON_ERROR)))
        ->toBe('{"success":true,"data":{"ok":true}}');
});

test('all six int refinement forms pass together', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', json_encode(BOUNDS_CHECK_VALID, JSON_THROW_ON_ERROR)))
        ->toBe('{"success":true,"data":{"ok":true}}');
});

test('the half-open int ranges accept extreme values on their open side', function () {
    expect(IntegrationHarness::queryJson(
        'inventory.boundsCheck',
        '{"debt":-5,"delta":-999999,"drop":-2,"floor":999999,"growth":3,"level":7}',
    ))->toBe('{"success":true,"data":{"ok":true}}');
});

test('numeric-string rejects a non-numeric value', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('amount', '12x')))
        ->toBe(refinementFailure('amount', 'validation.not_numeric_string'));
});

test('non-empty-string rejects the empty string', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('code', '')))
        ->toBe(refinementFailure('code', 'validation.not_empty_string'));
});

test('non-falsy-string rejects the string zero', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('comment', '0')))
        ->toBe(refinementFailure('comment', 'validation.falsy_string'));
});

test('truthy-string rejects the empty string with the falsy key', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('memo', '')))
        ->toBe(refinementFailure('memo', 'validation.falsy_string'));
});

test('lowercase-string rejects mixed case', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('slug', 'Mixed')))
        ->toBe(refinementFailure('slug', 'validation.not_lowercase_string'));
});

test('uppercase-string rejects lowercase', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('ticker', 'abc')))
        ->toBe(refinementFailure('ticker', 'validation.not_uppercase_string'));
});

test('non-empty-uppercase-string rejects lowercase content', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('label', 'abc')))
        ->toBe(refinementFailure('label', 'validation.not_uppercase_string'));
});

test('non-empty-lowercase-string reports only the emptiness when the value is empty', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('tag', '')))
        ->toBe(refinementFailure('tag', 'validation.not_empty_string'));
});

test('non-empty-lowercase-string rejects uppercase content', function () {
    expect(IntegrationHarness::queryJson('inventory.qualityGate', qualityGateWith('tag', 'ABC')))
        ->toBe(refinementFailure('tag', 'validation.not_lowercase_string'));
});

test('positive-int rejects zero', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', boundsCheckWith('growth', 0)))
        ->toBe(refinementFailure('growth', 'validation.invalid_min'));
});

test('negative-int rejects zero', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', boundsCheckWith('drop', 0)))
        ->toBe(refinementFailure('drop', 'validation.invalid_max'));
});

test('non-negative-int rejects minus one', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', boundsCheckWith('level', -1)))
        ->toBe(refinementFailure('level', 'validation.invalid_min'));
});

test('non-positive-int rejects one', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', boundsCheckWith('debt', 1)))
        ->toBe(refinementFailure('debt', 'validation.invalid_max'));
});

test('int with an open upper bound rejects below its minimum', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', boundsCheckWith('floor', -1)))
        ->toBe(refinementFailure('floor', 'validation.invalid_min'));
});

test('int with an open lower bound rejects above its maximum', function () {
    expect(IntegrationHarness::queryJson('inventory.boundsCheck', boundsCheckWith('delta', 1)))
        ->toBe(refinementFailure('delta', 'validation.invalid_max'));
});
