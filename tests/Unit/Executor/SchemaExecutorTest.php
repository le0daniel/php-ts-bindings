<?php

namespace Tests\Unit\Executor;

use DateTimeImmutable;
use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use LogicException;
use Stringable;
use Tests\Mocks\ValueObjects\CreateAccountInput;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\EmptyValidationValueObject;
use Tests\Mocks\ValueObjects\ExplodingValueObject;
use Tests\Mocks\ValueObjects\StatusEnum;
use Tests\Mocks\ValueObjects\UserId;
use Tests\Mocks\ValueObjects\ValidatedAge;
use Tests\Mocks\ValueObjects\ValidatedEmail;
use Tests\Unit\Executor\Mocks\ApiCredentials;
use Tests\Unit\Executor\Mocks\AuditedNoteInput;
use Tests\Unit\Executor\Mocks\UpdateProfileInput;
use Tests\Unit\Executor\Mocks\UserSchema;
use Tests\Unit\Parser\Data\Stubs\UncastableClass;
use ValueError;

test('parse success', function (string $type, mixed $value, mixed $expected) {
    $result = executeParse($type, $value);
    expect($result)->toBeSuccess();

    if (is_object($expected)) {
        expect($result->value)->toBeInstanceOf(get_class($expected));
        expect($result->value)->toEqual($expected);

        return;
    }
    expect($result->value)->toBe($expected);
})->with([
    ['string', 'my value', 'my value'],
    ['string[]|null', ['my value'], ['my value']],
    ['string[]|null', null, null],

    ['\DateTime|null', null, null],
    ['\DateTimeImmutable|null', '2025-09-10T12:09:01+00:00', DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2025-09-10 12:09:01')],

    ['string|null', 'my value', 'my value'],
    ['?string', 'my value', 'my value'],
    ['null|int|string', 'my value', 'my value'],
    ['null|int|string', null, null],
    ['string|int', 1, 1],
    ['array{int, string}', [1, 'my value'], [1, 'my value']],
    ['array{0: int,1: string}', [1, 'my value'], [1, 'my value']],
    ['array{id?: string, name: string}', ['id' => 'my id', 'name' => 'my name'], ['id' => 'my id', 'name' => 'my name']],
    ['array{id?: string, name: string}', ['name' => 'my name', 'other' => ''], ['name' => 'my name']],
    ['object{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', null, null],
    ['array<string, int>', ['my value' => 1], ['my value' => 1]],
    ['array<string, int>', [], []],

    // A JSON object key travels as a string; json_decode hands numeric ones back as PHP ints, and
    // an int keyed record wants exactly that.
    ['array<int, string>', ['0' => 'a', '1' => 'b'], [0 => 'a', 1 => 'b']],
    ['array<int, string>', ['42' => 'a'], [42 => 'a']],
    ["array<'one'|'two', string>", ['one' => 'a'], ['one' => 'a']],
    ["array<'one'|'two', string>", ['two' => 'b', 'one' => 'a'], ['two' => 'b', 'one' => 'a']],
    ['array<non-empty-string, int>', ['a' => 1], ['a' => 1]],
    ['array<positive-int, string>', ['1' => 'x'], [1 => 'x']],

    [UserSchema::class, (object) ['username' => 'my name', 'age' => 1, 'email' => 'leo@me.test'], new UserSchema(1, 'leo@me.test', 'my name')],
    [UserSchema::class, ['username' => 'my name', 'age' => 1, 'email' => 'leo@me.test'], new UserSchema(1, 'leo@me.test', 'my name')],

    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['id' => 1, 'reason' => 'my value'],
        ['id' => 1, 'reason' => 'my value'],
    ],
    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['token' => 'secret', 'reason' => 'my value'],
        ['token' => 'secret', 'reason' => 'my value'],
    ],
    [
        '(object{id:positive-int}|object{token:string})&object{reason:string}',
        ['id' => 1, 'reason' => 'my value'],
        (object) ['id' => 1, 'reason' => 'my value'],
    ],
    [
        '(object{id:positive-int}|object{token:string})&object{reason:string}',
        ['token' => 'secret', 'reason' => 'my value'],
        (object) ['token' => 'secret', 'reason' => 'my value'],
    ],
    [
        'Pick<object{id:positive-int, name: string}, "id">',
        ['id' => 1, 'name' => 'my name'],
        (object) ['id' => 1],
    ],

    [
        'Pick<array{id:positive-int, name: string}, "id">',
        ['id' => 1, 'name' => 'my name'],
        ['id' => 1],
    ],
    [
        'Omit<object{id:positive-int, name: string}, "id">',
        ['id' => 1, 'name' => 'my name'],
        (object) ['name' => 'my name'],
    ],
    [
        'Omit<array{id:positive-int, name: string}, "id">',
        ['id' => 1, 'name' => 'my name'],
        ['name' => 'my name'],
    ],

    // Value objects
    [Email::class, 'ada@example.test', Email::fromStringValue('ada@example.test')],
    [UserId::class, 42, UserId::fromIntValue(42)],
    ['?\\'.Email::class, null, null],
    [StatusEnum::class, 'active', StatusEnum::ACTIVE],
]);

test('serialize success', function (string $type, mixed $value, mixed $expected) {
    $result = executeSerialize($type, $value);

    expect($result)->toBeSuccess();

    if (is_object($expected)) {
        expect($result->value)->toBeInstanceOf(get_class($expected));
        expect($result->value)->toEqual($expected);

        return;
    }
    expect($result->value)->toBe($expected);
})->with([
    ['string', 'my value', 'my value'],
    ['string[]|null', ['my value'], ['my value']],
    ['string[]|null', null, null],

    ['\DateTime|null', null, null],
    ['\DateTime|null', DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2025-09-10 12:09:01'), '2025-09-10T12:09:01.000+00:00'],

    // Accept stringable values for output serialization
    ['string', new class () implements Stringable {
        public function __toString(): string
        {
            return 'my value';
        }
    }, 'my value'],
    ['string|null', 'my value', 'my value'],
    ['?string', 'my value', 'my value'],
    ['null|int|string', 'my value', 'my value'],
    ['null|int|string', null, null],
    ['string|int', 1, 1],
    ['array{int, string}', [1, 'my value'], [1, 'my value']],
    ['array{0: int,1: string}', [1, 'my value'], [1, 'my value']],
    ['array{id?: string, name: string}', ['id' => 'my id', 'name' => 'my name'], (object) ['id' => 'my id', 'name' => 'my name']],
    ['array{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', ['name' => 'my name', 'other' => ''], (object) ['name' => 'my name']],
    ['object{id?: string, name: string}|null', null, null],
    // Every array<...> leaves as an object, whatever its keys look like. See RecordWireShapeTest
    // for the JSON these actually encode to - that, not the PHP type, is the guarantee.
    ['array<string>', ['my value', 'my other value'], (object) ['my value', 'my other value']],
    ['array<string, int>', ['my value' => 1], (object) ['my value' => 1]],
    ['array<int, string>', [0 => 'a', 1 => 'b'], (object) [0 => 'a', 1 => 'b']],
    ['array<int, string>', [7 => 'a'], (object) [7 => 'a']],
    ['array<string, int>', [], (object) []],
    ["array<'one'|'two', string>", ['one' => 'a'], (object) ['one' => 'a']],
    ['list<string>', ['a', 'b'], ['a', 'b']],
    ['list<string>', [], []],

    [UserSchema::class, new UserSchema(1, 'leo@me.test', 'my name'), (object) ['username' => 'my name', 'age' => 1]],

    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['id' => 1, 'reason' => 'my value'],
        (object) ['id' => 1, 'reason' => 'my value'],
    ],
    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['token' => 'secret', 'reason' => 'my value'],
        (object) ['token' => 'secret', 'reason' => 'my value'],
    ],
    [
        '(object{id:positive-int}|object{token:string})&object{reason:string}',
        ['id' => 1, 'reason' => 'my value'],
        (object) ['id' => 1, 'reason' => 'my value'],
    ],
    [
        '(object{id:positive-int} | object{token:string}) & object{reason:string}',
        ['token' => 'secret', 'reason' => 'my value'],
        (object) ['token' => 'secret', 'reason' => 'my value'],
    ],
    [
        'Pick<object{id:positive-int, name: string}, "id">',
        (object) ['id' => 1, 'name' => 'my name'],
        (object) ['id' => 1],
    ],

    [
        'Pick<array{id:positive-int, name: string}, "id">',
        ['id' => 1, 'name' => 'my name'],
        (object) ['id' => 1],
    ],
    [
        'Omit<object{id:positive-int, name: string}, "id">',
        (object) ['id' => 1, 'name' => 'my name'],
        (object) ['name' => 'my name'],
    ],
    [
        'Omit<array{id:positive-int, name: string, other: string}, "id">',
        ['id' => 1, 'name' => 'my name', 'other' => 'string'],
        (object) ['name' => 'my name', 'other' => 'string'],
    ],
    [
        'Omit< \\'.UserSchema::class.', "age">',
        new UserSchema(12, 'email', 'username'),
        (object) ['username' => 'username'],
    ],
    [
        'Pick< \\'.UserSchema::class.', "age">',
        new UserSchema(12, 'email', 'username'),
        (object) ['age' => 12],
    ],

    // Value objects
    [Email::class, Email::fromStringValue('ada@example.test'), 'ada@example.test'],
    [UserId::class, UserId::fromIntValue(42), 42],
    ['?\\'.Email::class, null, null],
    // Serializes by backing value, NOT by the enum case name
    [StatusEnum::class, StatusEnum::INACTIVE, 'inactive'],
    ['\\'.Email::class.'[]', [Email::fromStringValue('a@b.test')], ['a@b.test']],
    [
        'array{id: \\'.UserId::class.', email: \\'.Email::class.'}',
        ['id' => UserId::fromIntValue(7), 'email' => Email::fromStringValue('ada@example.test')],
        (object) ['id' => 7, 'email' => 'ada@example.test'],
    ],
]);

test('serialization with partial failures', function () {
    /** @var Success $result */
    $result = executeSerialize('array{name: string|null, other: string}', [
        'name' => 123,
        'other' => 'my value',
    ]);

    expect($result)->toBeSuccess()
        ->and($result->value)->toEqual((object) [
            'name' => null,
            'other' => 'my value',
        ])->and($result->isPartial())->toBeTrue();
});

test('test error messages', function () {
    /** @var Success $result */
    $result = executeSerialize('array{name: string|null, other: string}', [
        'other' => 'my value',
    ]);

    expect($result)->toBeFailureAt('name');
    expect($result->issues->serializeToFieldsArray())->toEqual([
        'name' => [
            'validation.missing_property',
        ],
    ]);
});

/**
 * ---------------------------------------------------------------------------
 * Value objects
 * ---------------------------------------------------------------------------
 */
test('value object rejects the wrong primitive type', function () {
    expect(executeParse(UserId::class, '42'))->toBeFailure('validation.invalid_type');
    expect(executeParse(Email::class, 123))->toBeFailure('validation.invalid_type');
    expect(executeParse(Email::class, ['a' => 'b']))->toBeFailure('validation.invalid_type');
    expect(executeParse(Email::class, null))->toBeFailure('validation.invalid_type');
});

/**
 * A rejected value is not a wrong type. parseValue() proves the backing string or int before the
 * factory ever runs, so by the time one throws, the type is exactly what was declared and only the
 * value is at fault - which is what the two keys have to keep apart.
 */
test('value object reports a throwing factory as an invalid value, not an invalid type', function () {
    expect(executeParse(Email::class, 'not-an-email'))->toBeFailure('validation.invalid_value');
    expect(executeParse(UserId::class, 0))->toBeFailure('validation.invalid_value');
    expect(executeParse(UserId::class, -1))->toBeFailure('validation.invalid_value');

    $result = executeParse(Email::class, 'not-an-email');
    $messages = array_map(fn (Issue $issue) => $issue->messageOrLocalizationKey, $result->issues->allFlat());
    expect($messages)->not->toContain('internal_error')
        ->and($messages)->not->toContain('validation.invalid_type');
});

test('the original exception is attached to the issue for debugging', function () {
    $result = executeParse(Email::class, 'not-an-email');
    $issue = $result->issues->allFlat()[0];

    expect($issue->messageOrLocalizationKey)->toBe('validation.invalid_value')
        ->and($issue->exception)->toBeInstanceOf(InvalidArgumentException::class)
        ->and($issue->exception->getMessage())->toBe('Invalid email: not-an-email');
});

test('an Error thrown by the factory is caught, not just an Exception', function () {
    // StatusEnum::fromStringValue() delegates to self::from(), which throws \ValueError.
    // \ValueError extends Error, NOT Exception, so catching Exception would let it escape.
    $result = executeParse(StatusEnum::class, 'not-a-case');

    expect($result)->toBeFailure('validation.invalid_value')
        ->and($result->issues->allFlat()[0]->exception)->toBeInstanceOf(ValueError::class);
});

test('a throwing accessor on the serialize path is an internal error, not a validation issue', function () {
    $result = executeSerialize(ExplodingValueObject::class, ExplodingValueObject::fromStringValue('x'));

    expect($result)->toBeFailure('internal_error')
        ->and($result->issues->allFlat()[0]->exception)->toBeInstanceOf(LogicException::class);
});

test('a throwing accessor never escapes the executor', function () {
    expect(fn () => executeSerialize(
        'array{a: \\'.ExplodingValueObject::class.'}',
        ['a' => ExplodingValueObject::fromStringValue('x')],
    ))->not->toThrow(LogicException::class);
});

test('a throwing accessor degrades to null at a nullable boundary', function () {
    /** @var Success $result */
    $result = executeSerialize(
        'array{a: ?\\'.ExplodingValueObject::class.'}',
        ['a' => ExplodingValueObject::fromStringValue('x')],
    );

    expect($result)->toBeSuccess()
        ->and($result->value)->toEqual((object) ['a' => null])
        ->and($result->isPartial())->toBeTrue();
});

test('value objects nested in structs and lists hydrate correctly', function () {
    // Not in the 'parse success' dataset: that helper compares with toBe(), which is identity
    // based for objects nested inside an array.
    $struct = executeParse(
        'array{id: \\'.UserId::class.', email: \\'.Email::class.'}',
        ['id' => 1, 'email' => 'ada@example.test'],
    );

    expect($struct)->toBeSuccess()
        ->and($struct->value)->toEqual([
            'email' => Email::fromStringValue('ada@example.test'),
            'id' => UserId::fromIntValue(1),
        ]);

    $list = executeParse('\\'.Email::class.'[]', ['a@b.test', 'c@d.test']);

    expect($list)->toBeSuccess()
        ->and($list->value)->toEqual([
            Email::fromStringValue('a@b.test'),
            Email::fromStringValue('c@d.test'),
        ]);
});

/**
 * ---------------------------------------------------------------------------
 * Value objects rejecting with ValidationException
 * ---------------------------------------------------------------------------
 */
test('a ValidationException names the message the client sees, instead of validation.invalid_value', function () {
    $result = executeParse(ValidatedAge::class, 12);

    expect($result)->toBeFailure('Must be 18 or older')
        ->and($result->issues->serializeToFieldsArray())->toBe([
            '__root' => ['Must be 18 or older'],
        ]);
});

test('every message becomes its own issue at the same path, in order', function () {
    $result = executeParse(ValidatedEmail::class, '');

    expect($result->issues->serializeToFieldsArray())->toBe([
        '__root' => ['Email is required', 'Email must contain an @'],
    ]);
});

test('the exception and its debug info ride along on every issue it produced', function () {
    $result = executeParse(ValidatedEmail::class, 'nope');
    $issue = $result->issues->allFlat()[0];

    expect($result->issues->allFlat())->toHaveCount(1)
        ->and($issue->exception)->toBeInstanceOf(ValidationException::class)
        ->and($issue->debugInfo)->toHaveKey('value', 'nope')
        ->and($issue->debugInfo)->toHaveKey('node', ValueObjectNode::class);
});

test('the messages are reported at the field the value object sits at, not the root', function () {
    // Only one property may fail here: StructHandler::parse() returns on the first invalid one, so
    // a second rejecting field would never be reached.
    $result = executeParse(
        'array{email: \\'.ValidatedEmail::class.', age: \\'.ValidatedAge::class.'}',
        ['email' => '', 'age' => 30],
    );

    expect($result)->toBeFailureAt('email', 'Email must contain an @')
        ->and($result->issues->serializeToFieldsArray())->toBe([
            'email' => ['Email is required', 'Email must contain an @'],
        ]);
});

test('a ValidationException thrown for a list entry is reported at that index', function () {
    $result = executeParse('\\'.ValidatedEmail::class.'[]', ['a@b.test', 'nope']);

    expect($result->issues->serializeToFieldsArray())->toBe([
        '1' => ['Email must contain an @'],
    ]);
});

/**
 * The generic Throwable arm still exists and still collapses to a single key. Only a value object
 * that opts in by throwing ValidationException gets to name its messages.
 */
test('any other Throwable keeps collapsing to validation.invalid_value', function () {
    expect(executeParse(Email::class, 'not-an-email'))->toBeFailure('validation.invalid_value');
});

/**
 * The constructor guard throws before the ValidationException exists, so the generic arm catches it
 * and the field is still rejected with a message - never a Failure carrying no issues at all.
 */
test('a ValidationException built with no messages degrades instead of rejecting silently', function () {
    $result = executeParse(EmptyValidationValueObject::class, 'anything');

    expect($result)->toBeFailure('validation.invalid_value')
        ->and($result->issues->allFlat()[0]->exception)->toBeInstanceOf(InvalidArgumentException::class);
});

/**
 * ---------------------------------------------------------------------------
 * DateTimeString
 * ---------------------------------------------------------------------------
 */
test('DateTimeString parses a string into a DateTimeImmutable', function (string $type, string $value, string $expected) {
    $result = executeParse($type, $value);

    expect($result)->toBeSuccess()
        ->and($result->value)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($result->value->format('Y-m-d H:i:s.u P'))->toBe($expected);
})->with([
    // The shape that used to be the only accepted one. Kept as a backwards compatibility guard.
    'atom, the former default' => ['DateTimeString', '2025-09-10T12:09:01+00:00', '2025-09-10 12:09:01.000000 +00:00'],

    // Fields the format does not parse are zeroed out rather than inherited from the
    // current clock, so the result is deterministic.
    'date only' => ["DateTimeString<'Y-m-d'>", '2025-01-01', '2025-01-01 00:00:00.000000 +00:00'],
    'time only' => ["DateTimeString<'H:i'>", '08:30', '1970-01-01 08:30:00.000000 +00:00'],
    'custom format' => ["DateTimeString<'d.m.Y H:i'>", '01.02.2025 08:30', '2025-02-01 08:30:00.000000 +00:00'],

    // Lowercase p renders UTC as Z, which is the shape Date.toISOString() produces.
    'lowercase p accepts Z' => ["DateTimeString<'Y-m-d\\TH:i:sp'>", '2025-09-10T12:09:01Z', '2025-09-10 12:09:01.000000 +00:00'],
    'lowercase p accepts an offset' => ["DateTimeString<'Y-m-d\\TH:i:sp'>", '2025-09-10T12:09:01+02:00', '2025-09-10 12:09:01.000000 +02:00'],
]);

test('DateTimeString rejects input that does not match the format exactly', function (string $type, mixed $value) {
    expect(executeParse($type, $value))->toBeFailure('validation.invalid_type');
})->with([
    // createFromFormat() silently accepts these, so only the re-format round trip catches them.
    'single digit month and day' => ["DateTimeString<'Y-m-d'>", '2025-1-1'],
    'day out of range' => ["DateTimeString<'Y-m-d'>", '2025-02-30'],
    'month and day out of range' => ["DateTimeString<'Y-m-d'>", '2025-13-45'],

    'trailing data' => ["DateTimeString<'Y-m-d'>", '2025-01-01T10:00:00'],
    'not a date' => ["DateTimeString<'Y-m-d'>", 'not-a-date'],
    'empty string' => ["DateTimeString<'Y-m-d'>", ''],
    'whitespace' => ["DateTimeString<'Y-m-d'>", ' 2025-01-01'],
    'wrong format' => ["DateTimeString<'Y-m-d'>", '01.02.2025'],

    'int' => ["DateTimeString<'Y-m-d'>", 123],
    'null' => ["DateTimeString<'Y-m-d'>", null],
    'array' => ["DateTimeString<'Y-m-d'>", []],
    'bool' => ["DateTimeString<'Y-m-d'>", true],
    'an already hydrated date' => ["DateTimeString<'Y-m-d'>", new DateTimeImmutable('2025-01-01')],
]);

/**
 * Without a written format the type is ISO-8601, not one spelling of it. `Date.toISOString()` is
 * what actually crosses the wire from a browser, so it is what has to arrive intact.
 */
test('the default DateTime accepts every ISO-8601 shape a client sends', function (string $type, string $value, string $expected) {
    $result = executeParse($type, $value);

    expect($result)->toBeSuccess()
        ->and($result->value)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($result->value->format('Y-m-d H:i:s.u P'))->toBe($expected);
})->with([
    // The whole point of the change.
    'js toISOString' => ['DateTimeString', '2026-08-18T11:00:32.778Z', '2026-08-18 11:00:32.778000 +00:00'],
    'js toISOString on the bare class' => ['\DateTimeImmutable', '2026-08-18T11:00:32.778Z', '2026-08-18 11:00:32.778000 +00:00'],
    'zeroed milliseconds' => ['DateTimeString', '2026-08-18T11:00:32.000Z', '2026-08-18 11:00:32.000000 +00:00'],

    // Milliseconds with a real offset, and with the +00:00 spelling of UTC that is what
    // serializeValue() writes back.
    'milliseconds with an offset' => ['DateTimeString', '2026-08-18T11:00:32.778+02:00', '2026-08-18 11:00:32.778000 +02:00'],
    'milliseconds with an explicit +00:00' => ['DateTimeString', '2026-08-18T11:00:32.778+00:00', '2026-08-18 11:00:32.778000 +00:00'],

    'no fraction with a Z' => ['DateTimeString', '2026-08-18T11:00:32Z', '2026-08-18 11:00:32.000000 +00:00'],
    'no fraction with an offset' => ['DateTimeString', '2026-08-18T11:00:32+02:00', '2026-08-18 11:00:32.000000 +02:00'],
    'a negative offset' => ['DateTimeString', '2026-08-18T11:00:32-05:00', '2026-08-18 11:00:32.000000 -05:00'],

    // Backwards compatible: ATOM used to be the only accepted shape and is still accepted.
    'atom stays accepted' => ['DateTimeString', '2025-09-10T12:09:01+00:00', '2025-09-10 12:09:01.000000 +00:00'],
    'atom on the bare class' => ['\DateTimeImmutable', '2025-09-10T12:09:01+00:00', '2025-09-10 12:09:01.000000 +00:00'],

    'a leap day' => ['DateTimeString', '2024-02-29T00:00:00Z', '2024-02-29 00:00:00.000000 +00:00'],
]);

/**
 * More accepted spellings is not the same as a looser check: every candidate is still held to the
 * same re-format round trip, so everything that was nonsense before is still nonsense.
 */
test('the default DateTime still rejects everything that is not ISO-8601', function (mixed $value) {
    expect(executeParse('DateTimeString', $value))->toBeFailure('validation.invalid_type');
})->with([
    'a two digit fraction' => ['2026-08-18T11:00:32.77Z'],

    // Six digit microseconds are deliberately out of the accepted set. Clients that send them
    // write DateTimeString<'Y-m-d\TH:i:s.up'>.
    'microseconds' => ['2026-08-18T11:00:32.778123Z'],

    'a fraction with no zone' => ['2026-08-18T11:00:32.778'],
    'no zone at all' => ['2026-08-18T11:00:32'],
    'a lowercase z' => ['2026-08-18T11:00:32z'],
    'an offset without the colon' => ['2026-08-18T11:00:32.778+0200'],
    'a space instead of the T' => ['2026-08-18 11:00:32Z'],
    'a date with no time' => ['2026-08-18'],

    'a month and day out of range' => ['2026-13-45T11:00:32Z'],
    'an hour out of range' => ['2026-08-18T25:00:32Z'],
    'a day that does not exist that year' => ['2026-02-29T00:00:00Z'],
    'a rolling over day' => ['2026-02-30T00:00:00Z'],
    'single digit month and day' => ['2026-8-18T11:00:32Z'],

    'trailing whitespace' => ['2026-08-18T11:00:32.778Z '],
    'leading whitespace' => [' 2026-08-18T11:00:32Z'],
    'a doubled zone' => ['2026-08-18T11:00:32.778ZZ'],
    'an empty fraction' => ['2026-08-18T11:00:32.Z'],

    'not a date' => ['not-a-date'],
    'empty string' => [''],
    'an int' => [123],
    'null' => [null],
    'an already hydrated date' => [new DateTimeImmutable('2026-08-18')],
]);

/**
 * Lenient in, one shape out. Whatever spelling arrives, exactly one leaves, so the generated
 * TypeScript `string` has a single meaning and anything written here parses back in.
 */
test('the default DateTime canonicalises any accepted shape to RFC3339_EXTENDED', function (string $input, string $expected) {
    $parsed = executeParse('DateTimeString', $input);
    expect($parsed)->toBeSuccess();

    $serialized = executeSerialize('DateTimeString', $parsed->value);
    expect($serialized)->toBeSuccess()->and($serialized->value)->toBe($expected);
})->with([
    'atom' => ['2026-08-18T11:00:32+00:00', '2026-08-18T11:00:32.000+00:00'],
    'a Z' => ['2026-08-18T11:00:32Z', '2026-08-18T11:00:32.000+00:00'],
    'the js shape' => ['2026-08-18T11:00:32.778Z', '2026-08-18T11:00:32.778+00:00'],
    'already canonical' => ['2026-08-18T11:00:32.778+00:00', '2026-08-18T11:00:32.778+00:00'],

    // The offset survives; it is not normalised to UTC on the way through.
    'an offset' => ['2026-08-18T11:00:32.778+02:00', '2026-08-18T11:00:32.778+02:00'],
]);

test('a mutable DateTime takes the same ISO-8601 default', function (string $value) {
    $result = executeParse('\DateTime', $value);

    expect($result)->toBeSuccess()
        ->and($result->value)->toBeInstanceOf(\DateTime::class)
        ->and($result->value)->not->toBeInstanceOf(DateTimeImmutable::class)
        ->and($result->value->format('Y-m-d H:i:s.u P'))->toBe('2026-08-18 11:00:32.778000 +00:00');
})->with([
    'js toISOString' => ['2026-08-18T11:00:32.778Z'],
    'the canonical output shape' => ['2026-08-18T11:00:32.778+00:00'],
]);

/**
 * Writing the format down is a contract, and a contract is not widened behind its author's back.
 * This is the whole reason the default is a null sentinel rather than an overloaded ATOM.
 */
test('a written format stays exact, including one that spells out ATOM', function (string $type, string $value) {
    expect(executeParse($type, $value))->toBeFailure('validation.invalid_type');
})->with([
    'explicit ATOM refuses a Z' => ["DateTimeString<'Y-m-d\\TH:i:sP'>", '2026-08-18T11:00:32Z'],
    'explicit ATOM refuses milliseconds' => ["DateTimeString<'Y-m-d\\TH:i:sP'>", '2026-08-18T11:00:32.778Z'],
    'a millisecond format refuses a bare second' => ["DateTimeString<'Y-m-d\\TH:i:s.vp'>", '2026-08-18T11:00:32Z'],
    'a date only format refuses a full timestamp' => ["DateTimeString<'Y-m-d'>", '2026-08-18T11:00:32.778Z'],
]);

test('a written format still accepts exactly what it spells out', function (string $type, string $value) {
    expect(executeParse($type, $value))->toBeSuccess();
})->with([
    'explicit ATOM takes ATOM' => ["DateTimeString<'Y-m-d\\TH:i:sP'>", '2026-08-18T11:00:32+00:00'],
    'a millisecond format takes the js shape' => ["DateTimeString<'Y-m-d\\TH:i:s.vp'>", '2026-08-18T11:00:32.778Z'],
    'a microsecond format takes six digits' => ["DateTimeString<'Y-m-d\\TH:i:s.up'>", '2026-08-18T11:00:32.778123Z'],
]);

test('a written millisecond format round trips the js shape untouched', function () {
    $type = "DateTimeString<'Y-m-d\\TH:i:s.vp'>";
    $parsed = executeParse($type, '2026-08-18T11:00:32.778Z');
    expect($parsed)->toBeSuccess();

    expect(executeSerialize($type, $parsed->value)->value)->toBe('2026-08-18T11:00:32.778Z');
});

/**
 * The default's message names ISO-8601 and shows examples rather than listing PHP format strings at
 * a JavaScript developer. Examples in an error message rot silently, so they are asserted to be
 * things the node actually accepts.
 */
test('the default DateTime error message only names shapes it accepts', function () {
    $failure = executeParse('DateTimeString', 'nope');
    expect($failure)->toBeFailure('validation.invalid_type');

    $message = $failure->issues->allFlat()[0]->debugInfo['message'];
    preg_match_all("/'([^']+)'/", (string) $message, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $example) {
        expect(executeParse('DateTimeString', $example))->toBeSuccess();
    }
});

test('DateTimeString serializes a date back to its format', function (string $type, mixed $value, string $expected) {
    $result = executeSerialize($type, $value);

    expect($result)->toBeSuccess()->and($result->value)->toBe($expected);
})->with([
    'immutable' => ["DateTimeString<'Y-m-d'>", new DateTimeImmutable('2025-01-01 10:11:12'), '2025-01-01'],
    'mutable' => ["DateTimeString<'Y-m-d'>", new \DateTime('2025-01-01 10:11:12'), '2025-01-01'],
    'default RFC3339_EXTENDED format' => ['DateTimeString', new DateTimeImmutable('2025-09-10 12:09:01'), '2025-09-10T12:09:01.000+00:00'],
    'custom format' => ["DateTimeString<'d.m.Y H:i'>", new DateTimeImmutable('2025-02-01 08:30:00'), '01.02.2025 08:30'],
]);

test('DateTimeString rejects a non date on serialization', function (mixed $value) {
    expect(executeSerialize("DateTimeString<'Y-m-d'>", $value))->toBeFailure('validation.invalid_type');
})->with([
    'a formatted string' => ['2025-01-01'],
    'an int' => [123],
    'null' => [null],
    'an array' => [[]],
]);

test('DateTimeString round trips through parse and serialize', function (string $type, string $value) {
    $parsed = executeParse($type, $value);
    expect($parsed)->toBeSuccess();

    expect(executeSerialize($type, $parsed->value))->toBeSuccess()
        ->and(executeSerialize($type, $parsed->value)->value)->toBe($value);
})->with([
    // The default only round trips its own output shape; every other accepted shape canonicalises
    // instead, which the canonicalisation test covers.
    ['DateTimeString', '2025-09-10T12:09:01.000+00:00'],
    ["DateTimeString<'Y-m-d'>", '2025-01-01'],
    ["DateTimeString<'d.m.Y H:i'>", '01.02.2025 08:30'],
    ["DateTimeString<'Y-m-d\\TH:i:sp'>", '2025-09-10T12:09:01Z'],
]);

test('value object issues are reported at the right field path', function () {
    $result = executeParse('array{email: \\'.Email::class.'}', ['email' => 'nope']);

    expect($result)->toBeFailureAt('email', 'validation.invalid_value');
});

test('value object coerces primitives when coercion is enabled', function () {
    $result = executeParse(UserId::class, '42', new ParsingOptions(coercePrimitives: true));
    expect($result)->toBeSuccess()
        ->and($result->value)->toEqual(UserId::fromIntValue(42));

    $result = executeParse(Email::class, 'ada@example.test', new ParsingOptions(coercePrimitives: true));
    expect($result)->toBeSuccess()
        ->and($result->value)->toEqual(Email::fromStringValue('ada@example.test'));
});

test('serializing something that is not the value object fails', function () {
    expect(executeSerialize(Email::class, 'ada@example.test'))->toBeFailure('validation.invalid_type');
    expect(executeSerialize(Email::class, UserId::fromIntValue(1)))->toBeFailure('validation.invalid_type');
    expect(executeSerialize(UserId::class, null))->toBeFailure('validation.invalid_type');
    expect(executeSerialize(UserId::class, 42))->toBeFailure('validation.invalid_type');
});

test('nullable value objects tolerate null at the union boundary', function () {
    expect(executeSerialize('?\\'.Email::class, null))->toBeSuccess();
    expect(executeParse('?\\'.Email::class, null))->toBeSuccess();
});

test('a castable class hydrates and serializes its value object properties', function () {
    $parsed = executeParse(CreateAccountInput::class, [
        'email' => 'ada@example.test',
        'ownerId' => 7,
    ]);

    expect($parsed)->toBeSuccess()
        ->and($parsed->value)->toBeInstanceOf(CreateAccountInput::class)
        ->and($parsed->value->email)->toEqual(Email::fromStringValue('ada@example.test'))
        ->and($parsed->value->ownerId)->toEqual(UserId::fromIntValue(7));

    $serialized = executeSerialize(CreateAccountInput::class, $parsed->value);

    expect($serialized)->toBeSuccess()
        ->and($serialized->value)->toEqual((object) ['email' => 'ada@example.test', 'ownerId' => 7]);
});

test('assign-properties hydration applies set hooks and drops output-only payload keys', function () {
    $parsed = executeParse(UpdateProfileInput::class, [
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'password' => 'secret',
        'displayName' => '  ada  ',
        // Assigning any of these would throw; they must be dropped before hydration.
        'fullName' => 'decoy',
        'passwordHash' => 'decoy',
        'unknown' => 'decoy',
    ]);

    expect($parsed)->toBeSuccess()
        ->and($parsed->value)->toBeInstanceOf(UpdateProfileInput::class)
        ->and($parsed->value->firstName)->toBe('Ada')
        ->and($parsed->value->lastName)->toBe('Lovelace')
        ->and($parsed->value->displayName)->toBe('ada')
        ->and($parsed->value->passwordHash)->toBe('terces');
});

test('assign-properties serialization reads get hooks and skips write-only properties', function () {
    $profile = new UpdateProfileInput();
    $profile->firstName = 'Ada';
    $profile->lastName = 'Lovelace';
    $profile->password = 'secret';
    $profile->displayName = 'ada';

    $serialized = executeSerialize(UpdateProfileInput::class, $profile);

    expect($serialized)->toBeSuccess()
        ->and($serialized->value)->toEqual((object) [
            'displayName' => 'ada',
            'firstName' => 'Ada',
            'fullName' => 'Ada Lovelace',
            'lastName' => 'Lovelace',
            'passwordHash' => 'terces',
        ]);
});

test('a missing write-only virtual property fails the parse', function () {
    $result = executeParse(UpdateProfileInput::class, [
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'displayName' => 'ada',
    ]);

    expect($result)->toBeFailure('validation.missing_property');
});

test('the zero-argument constructor runs and readonly output stays server-controlled', function () {
    $parsed = executeParse(AuditedNoteInput::class, [
        'note' => 'first entry',
        'recordedBy' => 'spoofed',
    ]);

    expect($parsed)->toBeSuccess()
        ->and($parsed->value)->toBeInstanceOf(AuditedNoteInput::class)
        ->and($parsed->value->note)->toBe('first entry')
        ->and($parsed->value->recordedBy)->toBe('system');

    $serialized = executeSerialize(AuditedNoteInput::class, $parsed->value);

    expect($serialized)->toBeSuccess()
        ->and($serialized->value)->toEqual((object) ['note' => 'first entry', 'recordedBy' => 'system']);
});

test('constructor casting hydrates hidden members and serializes only readable properties', function () {
    $parsed = executeParse(ApiCredentials::class, [
        'keyId' => 'key_123',
        'secret' => 'hunter2',
    ]);

    expect($parsed)->toBeSuccess()
        ->and($parsed->value)->toBeInstanceOf(ApiCredentials::class)
        ->and($parsed->value->keyId)->toBe('key_123');

    $parsed->value->plainSecret = 'hunter2';
    $serialized = executeSerialize(ApiCredentials::class, $parsed->value);

    expect($serialized)->toBeSuccess()
        ->and($serialized->value)->toEqual((object) ['keyId' => 'key_123', 'obfuscated' => '*******']);
});

test('an uncastable class fails on input but serializes to a plain object', function () {
    expect(executeParse(UncastableClass::class, ['email' => 'ada@example.test', 'name' => 'Ada']))
        ->toBeFailure();

    $serialized = executeSerialize(UncastableClass::class, new UncastableClass('ada@example.test', 'Ada'));

    expect($serialized)->toBeSuccess()
        ->and($serialized->value)->toEqual((object) ['email' => 'ada@example.test', 'name' => 'Ada']);
});
