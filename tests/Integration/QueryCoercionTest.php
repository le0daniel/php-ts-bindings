<?php

declare(strict_types=1);

use Tests\Integration\IntegrationHarness;

/**
 * Query input coercion end to end, the mode a public GET API runs in. A URL carries no types, so
 * every query parameter reaches the server as a string and `coerceQueryInput: true` is what turns
 * "1" back into 1 before validation. Coercion fires at leaves only, at any depth, and it can never
 * fail: a value it cannot convert is handed on untouched for the schema to reject.
 *
 * Every expectation here is the exact envelope the server produced. The harness runs each call
 * against both the eager and the disk-cached registry and asserts they agree, so each test pins the
 * optimized AST at the same time.
 */
const LIST_ORDERS_ROWS = '[{"orderNumber":"ORD-1001","status":"PAID"},{"orderNumber":"ORD-1002","status":"PENDING"}]';

/**
 * Payloads every refinement of their operation already accepts, so a case that swaps one key in
 * isolates that key: whatever the envelope says is about the value under test and nothing else.
 */
const BOUNDS_CHECK_BASELINE = ['debt' => 0, 'delta' => -3, 'drop' => -1, 'floor' => 0, 'growth' => 1, 'level' => 0];

const QUALITY_GATE_BASELINE = [
    'amount' => '12.5',
    'code' => 'x',
    'comment' => 'ok',
    'label' => 'UP',
    'memo' => 'y',
    'slug' => 'low',
    'tag' => 'tag',
    'ticker' => 'TCK',
];

function coercedQuery(string $key, string $json): string
{
    return IntegrationHarness::queryJson($key, $json, coerceQueryInput: true);
}

/**
 * `page` is the smallest int leaf in the fixture app that is also refined (positive-int), which
 * makes it the one place where "was it coerced?" and "was it then validated?" are both visible.
 */
function coercedPage(string $rawJsonValue): string
{
    return coercedQuery('orders.listOrders', '{"page":'.$rawJsonValue.'}');
}

function listOrdersOk(int $page = 1, int $perPage = 20): string
{
    return '{"success":true,"data":{"orders":'.LIST_ORDERS_ROWS.',"page":'.$page.',"perPage":'.$perPage.'}}';
}

function coercionFailure(string $field, string ...$issueKeys): string
{
    return json_encode([
        'success' => false,
        'code' => 422,
        'type' => 'INVALID_INPUT',
        'details' => ['fields' => [$field => $issueKeys]],
    ], JSON_THROW_ON_ERROR);
}

/**
 * 1. Int coercion, the case the whole flag exists for.
 */
test('a string query parameter is coerced to the int it spells', function (string $rawJsonValue, int $expected) {
    expect(coercedPage($rawJsonValue))->toBe(listOrdersOk($expected));
})->with([
    // The headline case: ?page=1 over a real URL.
    'the plain "1"' => ['"1"', 1],
    'a multi digit string' => ['"42"', 42],
    // filter_var trims surrounding whitespace, so a padded parameter still lands.
    'surrounding whitespace' => ['" 3 "', 3],
    'an explicit plus sign' => ['"+4"', 4],
    'PHP_INT_MAX exactly' => ['"9223372036854775807"', 9223372036854775807],
    // Already the right type: nothing to do.
    'an int that needs no coercion' => ['7', 7],
    // filter_var reads both of these as ints, so they narrow losslessly.
    'a float with no fractional part' => ['1.0', 1],
    'the boolean true' => ['true', 1],
]);

test('an int query parameter that spells no int is rejected as a type error', function (string $rawJsonValue) {
    expect(coercedPage($rawJsonValue))->toBe(coercionFailure('page', 'validation.invalid_type'));
})->with([
    // FILTER_VALIDATE_INT is canonical-decimal only: no leading zeros, no exponent, no separators.
    'a leading zero' => ['"01"'],
    'a trailing .0' => ['"1.0"'],
    'exponent notation' => ['"1e2"'],
    'hexadecimal' => ['"0x1A"'],
    'a numeric separator' => ['"1_000"'],
    'plain text' => ['"abc"'],
    'the empty string' => ['""'],
    'only whitespace' => ['" "'],
    // Coercion never invents a value: a non-scalar is handed on untouched.
    'null' => ['null'],
    'a list' => ['[]'],
    'an object' => ['{}'],
    // Narrowing is lossless or not at all.
    'a float with a fractional part' => ['1.5'],
    // filter_var(false, FILTER_VALIDATE_INT) is false, so unlike true this one never converts.
    'the boolean false' => ['false'],
    // One past PHP_INT_MAX. Rejected outright rather than truncated or wrapped.
    'an int one past PHP_INT_MAX' => ['"9223372036854775808"'],
]);

/**
 * The refinement is not a leaf, so it runs on whatever the leaf produced. A value that coerces and
 * then fails its bounds reports the bound, which is what proves the order of the two.
 */
test('a coerced int is then judged by its refinement, not by its type', function (string $rawJsonValue) {
    expect(coercedPage($rawJsonValue))->toBe(coercionFailure('page', 'validation.invalid_min'));
})->with([
    'zero against positive-int' => ['"0"'],
    'a negative number' => ['"-1"'],
    // filter_var reads "-0" as the int 0, which positive-int then refuses.
    'negative zero' => ['"-0"'],
]);

test('an int range refinement applies both of its bounds after coercion', function () {
    expect(coercedQuery('orders.listOrders', '{"perPage":"100"}'))->toBe(listOrdersOk(perPage: 100));
    expect(coercedQuery('orders.listOrders', '{"perPage":"1"}'))->toBe(listOrdersOk(perPage: 1));
    expect(coercedQuery('orders.listOrders', '{"perPage":"101"}'))
        ->toBe(coercionFailure('perPage', 'validation.invalid_max'));
    expect(coercedQuery('orders.listOrders', '{"perPage":"0"}'))
        ->toBe(coercionFailure('perPage', 'validation.invalid_min'));
});

test('two coerced int parameters are validated independently', function () {
    expect(coercedQuery('orders.listOrders', '{"page":"2","perPage":"50"}'))->toBe(listOrdersOk(2, 50));
});

test('optional int keys stay absent rather than being coerced from nothing', function () {
    expect(coercedQuery('orders.listOrders', '{}'))->toBe(listOrdersOk());
});

/**
 * 2. The contrast pairs: the flag is what makes the difference, and it is queries only.
 */
test('the same int string is a type error when coercion is disabled', function () {
    expect(IntegrationHarness::queryJson('orders.listOrders', '{"page":"1"}'))
        ->toBe(coercionFailure('page', 'validation.invalid_type'));
});

test('commands never coerce an int, even for input a query accepts', function () {
    expect(IntegrationHarness::commandJson('cart.applyVoucher', '{"code":"SUMMER","percent":"15"}'))
        ->toBe(coercionFailure('percent', 'validation.invalid_type'));
});

/**
 * 3. Float. convertWeight is a bare float at the root, so its failures land on __root and there is
 * no struct in the way.
 */
test('a float query parameter is coerced from its string form', function (string $rawJsonValue, string $expected) {
    expect(coercedQuery('inventory.convertWeight', $rawJsonValue))
        ->toBe('{"success":true,"data":'.$expected.'}');
})->with([
    'a decimal string' => ['"2.5"', '2.5'],
    'an int string' => ['"1"', '1'],
    'exponent notation' => ['"1e3"', '1000'],
    'surrounding whitespace' => ['" 2.5 "', '2.5'],
    'the boolean true' => ['true', '1'],
    'an int that needs no coercion' => ['1', '1'],
    'a float that needs no coercion' => ['1.5', '1.5'],
]);

/**
 * The two filters disagree on exactly one shape, and a query parameter is where that shows: an
 * order number written 007 is not an int but is a perfectly good float.
 */
test('a leading zero is no int but is a float', function () {
    expect(coercedPage('"01"'))->toBe(coercionFailure('page', 'validation.invalid_type'));
    expect(coercedQuery('inventory.convertWeight', '"01"'))->toBe('{"success":true,"data":1}');
});

test('a float query parameter that spells no number is rejected at the root path', function (string $rawJsonValue) {
    expect(coercedQuery('inventory.convertWeight', $rawJsonValue))
        ->toBe(coercionFailure('__root', 'validation.invalid_type'));
})->with([
    'plain text' => ['"abc"'],
    'the boolean false' => ['false'],
    'null' => ['null'],
]);

/**
 * 4. Bool. BoolNode::coerce() is a match, which compares identically, so the accepted set is these
 * four strings and nothing else.
 */
test('a bool query parameter is coerced from the four strings that spell one', function (string $rawJsonValue, string $expected) {
    expect(coercedQuery('inventory.stockFlag', '{"inStock":'.$rawJsonValue.'}'))
        ->toBe('{"success":true,"data":{"inStock":'.$expected.'}}');
})->with([
    'the word true' => ['"true"', 'true'],
    'the word false' => ['"false"', 'false'],
    'the digit one' => ['"1"', 'true'],
    'the digit zero' => ['"0"', 'false'],
    'a real true' => ['true', 'true'],
    'a real false' => ['false', 'false'],
]);

test('a bool query parameter rejects every other spelling', function (string $rawJsonValue) {
    expect(coercedQuery('inventory.stockFlag', '{"inStock":'.$rawJsonValue.'}'))
        ->toBe(coercionFailure('inStock', 'validation.invalid_type'));
})->with([
    // No case folding, and none of the HTML form vocabulary.
    'uppercase TRUE' => ['"TRUE"'],
    'capitalised True' => ['"True"'],
    'the word yes' => ['"yes"'],
    'the word on' => ['"on"'],
    'a number that is not 0 or 1' => ['"2"'],
    'the empty string' => ['""'],
    // Whitespace is not trimmed here, unlike the int and float filters.
    'a padded digit' => ['" 1 "'],
    // The comparison is identical, so the int 1 is not the string "1".
    'the int one' => ['1'],
    'the int zero' => ['0'],
]);

/**
 * 5. String. Any scalar casts, and only a scalar: an array or object would have to be invented into
 * a string, so it is left for the schema to reject.
 */
test('a string leaf casts any scalar, in the same struct where an int leaf coerces', function () {
    expect(coercedQuery('catalog.describeLabels', '{"content-type":123,"count":"7"}'))
        ->toBe('{"success":true,"data":{"content-type":"123","count":7}}');
    expect(coercedQuery('catalog.describeLabels', '{"content-type":1.5,"count":"7"}'))
        ->toBe('{"success":true,"data":{"content-type":"1.5","count":7}}');
    expect(coercedQuery('catalog.describeLabels', '{"content-type":true,"count":"7"}'))
        ->toBe('{"success":true,"data":{"content-type":"1","count":7}}');
});

/**
 * 6. Refinements see the coerced value, so a string parameter can satisfy a refinement its raw form
 * never could, and can fail one on the strength of what it became.
 */
test('int refinements judge the coerced value', function (string $key, string $rawJsonValue, ?string $issueKey) {
    $payload = json_encode([...BOUNDS_CHECK_BASELINE, $key => json_decode($rawJsonValue, true, 512, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);

    expect(coercedQuery('inventory.boundsCheck', $payload))->toBe(
        $issueKey === null ? '{"success":true,"data":{"ok":true}}' : coercionFailure($key, $issueKey)
    );
})->with([
    'a positive-int from a string' => ['growth', '"5"', null],
    'a negative-int from a string' => ['drop', '"-2"', null],
    'a padded negative in an open range' => ['delta', '" -4 "', null],
    'zero coerced then refused by positive-int' => ['growth', '"0"', 'validation.invalid_min'],
    'one coerced then refused by non-positive-int' => ['debt', '"1"', 'validation.invalid_max'],
]);

test('string refinements judge what the scalar became', function (string $key, string $rawJsonValue, ?string $issueKey) {
    $payload = json_encode([...QUALITY_GATE_BASELINE, $key => json_decode($rawJsonValue, true, 512, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);

    expect(coercedQuery('inventory.qualityGate', $payload))->toBe(
        $issueKey === null ? '{"success":true,"data":{"ok":true}}' : coercionFailure($key, $issueKey)
    );
})->with([
    // A JSON number satisfies numeric-string once it has been cast to one.
    'a float satisfying numeric-string' => ['amount', '12.5', null],
    'a string that is no number' => ['amount', '"abc"', 'validation.not_numeric_string'],
    // false casts to the empty string, which is the one string non-empty-string rejects.
    'false against non-empty-string' => ['code', 'false', 'validation.not_empty_string'],
    // true casts to "1", which has no case and so is trivially uppercase.
    'true against non-empty-uppercase-string' => ['label', 'true', null],
    'an int against lowercase-string' => ['slug', '1', null],
    // "" is uppercase for the same reason, and uppercase-string does not require content.
    'false against uppercase-string' => ['ticker', 'false', null],
]);

/**
 * 7. Depth. No container coerces anything; each one recurses until it reaches a leaf, so a leaf
 * buried in a list of lists coerces exactly as a top level one does.
 */
test('coercion reaches leaves nested in lists of lists', function () {
    expect(coercedQuery('catalog.relatedSkus', '{"grid":[["1","2"],["3"]],"sku":"ABC-123"}'))
        ->toBe('{"success":true,"data":{"codes":["ABC-123"],"grid":[[1,2],[3]]}}');
});

test('a leaf that cannot coerce reports its full dotted path', function () {
    expect(coercedQuery('catalog.relatedSkus', '{"grid":[["1","x"]],"sku":"ABC-123"}'))
        ->toBe(coercionFailure('grid.0.1', 'validation.invalid_type'));
});

test('a tuple coerces its members in opposite directions at once', function () {
    expect(coercedQuery('catalog.dimensionsTuple', '{"box":["1",2]}'))
        ->toBe('{"success":true,"data":{"box":[1,"2"]}}');
});

test('tuple arity is checked before its members are coerced', function () {
    expect(coercedQuery('catalog.dimensionsTuple', '{"box":["1"]}'))
        ->toBe(coercionFailure('box', 'validation.invalid_type'));
    expect(coercedQuery('catalog.dimensionsTuple', '{"box":["x",2]}'))
        ->toBe(coercionFailure('box.0', 'validation.invalid_type'));
});

test('both arms of an intersection coerce', function () {
    expect(coercedQuery('catalog.searchFilters', '{"a":"1","b":2}'))
        ->toBe('{"success":true,"data":{"a":1,"b":"2"}}');
});

test('coercion applies per leaf inside a nested struct', function () {
    expect(coercedQuery('inventory.warehouseCapacity', '{"filters":{"includeEmpty":"1","limit":"5","ratio":"1.5"}}'))
        ->toBe('{"success":true,"data":{"includeEmpty":true,"limit":5,"ratio":1.5}}');
});

/**
 * 8. Unions. Arms are probed in declaration order and each coerces on its own, so which arm claims
 * a value depends on the order they were written in - the single most surprising consequence of
 * turning the flag on, and the one a public API notices.
 */
test('a union arm coerces on its own and the first one that matches wins', function () {
    // int|string: "1" is claimed by int, "01" and "1.5" are no ints so they fall through to string,
    // and true is an int to filter_var. One list shows all four outcomes.
    expect(coercedQuery('catalog.listOfUnions', '{"values":["1","abc","01","1.5",true]}'))
        ->toBe('{"success":true,"data":{"values":[1,"abc","01","1.5",1]}}');
});

test('the scalar shorthand resolves a string to the narrowest arm that claims it', function (string $rawJsonValue, string $expected) {
    expect(coercedQuery('inventory.normalizeCode', '{"value":'.$rawJsonValue.'}'))
        ->toBe('{"success":true,"data":{"value":'.$expected.'}}');
})->with([
    'an int string becomes an int' => ['"1"', '1'],
    'a decimal string becomes a float' => ['"1.5"', '1.5'],
    'the word true becomes a bool' => ['"true"', 'true'],
    'text stays a string' => ['"abc"', '"abc"'],
]);

test('the numeric shorthand accepts numeric strings once coercion is on', function () {
    expect(coercedQuery('inventory.sumNumeric', '{"a":"1","b":"2"}'))
        ->toBe('{"success":true,"data":{"total":3}}');
    expect(coercedQuery('inventory.sumNumeric', '{"a":"1.5","b":"2"}'))
        ->toBe('{"success":true,"data":{"total":3.5}}');
});

test('a union that no arm claims reports one issue per arm plus the union', function () {
    expect(coercedQuery('inventory.sumNumeric', '{"a":"abc","b":"2"}'))
        ->toBe(coercionFailure('a', 'validation.invalid_type', 'validation.invalid_type', 'validation.invalid_type'));
});

test('a discriminated union coerces inside the arm its discriminator selected', function () {
    expect(coercedQuery('catalog.feedEvents', '{"events":[{"kind":"restock","qty":"5"},{"kind":"sale","ref":"R"}]}'))
        ->toBe('{"success":true,"data":{"kinds":["restock","sale"],"total":2}}');
});

/**
 * 9. Null and absent. Coercion never manufactures a value, so neither of them becomes one.
 */
test('null is not coerced into anything', function () {
    // An optional key that is present and null is a type error; absent is what falls back.
    expect(coercedPage('null'))->toBe(coercionFailure('page', 'validation.invalid_type'));
    expect(coercedQuery('catalog.maybeInventory', '{"tags":null}'))
        ->toBe('{"success":true,"data":{"tags":null}}');
});

test('a nullable container coerces the elements of its non-null arm', function () {
    expect(coercedQuery('catalog.maybeInventory', '{"tags":[1,2]}'))
        ->toBe('{"success":true,"data":{"tags":["1","2"]}}');
});

test('an element that coerces to the empty string fails its refinement and the container arms', function () {
    // false casts to "", which non-empty-string rejects at tags.0; the list arm then fails as a
    // whole and the null arm rejects the list, so the union reports at tags too.
    expect(coercedQuery('catalog.maybeInventory', '{"tags":[false]}'))->toBe(json_encode([
        'success' => false,
        'code' => 422,
        'type' => 'INVALID_INPUT',
        'details' => ['fields' => [
            'tags.0' => ['validation.not_empty_string'],
            'tags' => ['validation.invalid_type', 'validation.invalid_type'],
        ]],
    ], JSON_THROW_ON_ERROR));
});

/**
 * 10. Value objects coerce to their backing primitive, then the factory judges the value. Which
 * of the two rejected the input is visible in the issue: a type error never reached the factory.
 */
test('an int backed value object is built from a string query parameter', function () {
    expect(coercedQuery('inventory.lookupWarehouse', '{"id":"5"}'))
        ->toBe('{"success":true,"data":{"id":5,"name":"Zurich Hub"}}');
    expect(coercedQuery('inventory.lookupWarehouse', '{"id":true}'))
        ->toBe('{"success":true,"data":{"id":1,"name":"Zurich Hub"}}');
});

test('a value object separates a bad type from a bad value', function () {
    // "0" is a perfectly good int, so the factory runs and speaks for itself.
    expect(coercedQuery('inventory.lookupWarehouse', '{"id":"0"}'))
        ->toBe(coercionFailure('id', 'Warehouse id must be positive'));
    // "01" is no int at all, so the factory is never reached.
    expect(coercedQuery('inventory.lookupWarehouse', '{"id":"01"}'))
        ->toBe(coercionFailure('id', 'validation.invalid_type'));
});

test('a string backed value object casts any scalar and then rejects the value', function () {
    expect(coercedQuery('orders.parcelDimensions', '{"sku":"ABC-123"}'))
        ->toBe('{"success":true,"data":{"dimensionsMm":[300,200,50],"sku":"ABC-123"}}');
    expect(coercedQuery('orders.parcelDimensions', '{"sku":123}'))
        ->toBe(coercionFailure('sku', 'Sku must match ABC-123'));
});

test('a backed enum coerces only when it opts into value object semantics', function () {
    // PalletSize implements IntValueObject, so it is a ValueObjectNode and coerces.
    // StockLevel is a plain backed enum: an EnumNode, matched on case name, never coerced.
    expect(coercedQuery('inventory.palletReport', '{"level":"LOW","size":"2"}'))
        ->toBe('{"success":true,"data":{"level":"LOW","size":2}}');
    expect(coercedQuery('inventory.palletReport', '{"level":"1","size":"2"}'))
        ->toBe(coercionFailure('level', 'validation.invalid_type'));
    // An int that names no case is a value the enum refuses, not a type it cannot read.
    expect(coercedQuery('inventory.palletReport', '{"level":"LOW","size":"9"}'))
        ->toBe(coercionFailure('size', 'validation.invalid_value'));
});

/**
 * 11. The nodes that deliberately never coerce. Each is a leaf, so it would be the executor's to
 * coerce if it implemented Coercible - it does not, and that is the behaviour being pinned.
 */
test('a castable class coerces its properties but a date never coerces', function () {
    expect(coercedQuery('catalog.mixedTuple', '{"entry":[{"amount":"100","currency":"chf"},"PAID","2024-05-01"]}'))
        ->toBe('{"success":true,"data":{"entry":[{"amount":100,"currency":"chf"},"PAID","2024-05-01"]}}');
    // A date is a strict format round trip, so an int that looks like one is still not one.
    expect(coercedQuery('catalog.mixedTuple', '{"entry":[{"amount":100,"currency":"chf"},"PAID",20240501]}'))
        ->toBe(coercionFailure('entry.2', 'validation.invalid_type'));
});

test('mixed passes its value through untouched', function () {
    expect(coercedQuery('inventory.echoMetadata', '{"meta":"1"}'))
        ->toBe('{"success":true,"data":{"meta":"1"}}');
    expect(coercedQuery('inventory.echoMetadata', '{"meta":"true"}'))
        ->toBe('{"success":true,"data":{"meta":"true"}}');
});

test('string literals are identity under coercion while int, float and bool literals are not', function () {
    // factor is 0.5|1.5 and flag is the false literal, so both coerce from their string forms.
    // mode is a union of class-constant strings and takes its exact value either way.
    expect(coercedQuery('inventory.literalSampler', '{"factor":"1.5","flag":"false","legacy":null,"mode":"express"}'))
        ->toBe('{"success":true,"data":{"factor":1.5,"flag":false,"legacy":null,"mode":"express"}}');
});

test('the null literal is not coerced from the word null', function () {
    expect(coercedQuery('inventory.literalSampler', '{"factor":0.5,"flag":false,"legacy":"null","mode":"express"}'))
        ->toBe(coercionFailure('legacy', 'validation.invalid_type'));
});

test('a string literal union takes its value verbatim', function () {
    expect(coercedQuery('orders.trackingEvent', '{"stage":"created"}'))
        ->toBe('{"success":true,"data":{"at":"2024-05-01","kind":"created"}}');
});

/**
 * 12. Records. Values are leaves like any other and coerce; keys are whatever PHP already made of
 * them, because a PHP array is a hash map that folds a canonical decimal string into an int before
 * the executor sees anything. `$a["1"] = x` stores the int key 1 no matter what this library does,
 * which is the same hole PHPStan has to live with, so a coerced key is not a wrong one.
 */
test('record values coerce while the key set stays what PHP made of it', function () {
    expect(coercedQuery('catalog.priceBuckets', '{"thresholds":{"low":"10","high":"20"}}'))
        ->toBe('{"success":true,"data":{"thresholds":{"low":10,"high":20}}}');
    expect(coercedQuery('catalog.ratingByStars', '{"votes":{"1":"10","5":"3"}}'))
        ->toBe('{"success":true,"data":{"votes":{"1":10,"5":3}}}');
});

test('a key that names no valid key is still rejected as a key', function () {
    expect(coercedQuery('catalog.ratingByStars', '{"votes":{"one":"10"}}'))
        ->toBe(coercionFailure('votes.one', 'validation.invalid_key_type'));
});

test('a record refinement counts entries after their values coerced', function () {
    expect(coercedQuery('catalog.priceBuckets', '{"thresholds":{}}'))
        ->toBe(coercionFailure('thresholds', 'validation.invalid_min'));
});
