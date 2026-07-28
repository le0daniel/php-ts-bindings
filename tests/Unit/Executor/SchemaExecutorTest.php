<?php

namespace Tests\Unit\Executor;

use DateTimeImmutable;
use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use LogicException;
use Stringable;
use ValueError;
use Tests\Mocks\ValueObjects\CreateAccountInput;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\ExplodingValueObject;
use Tests\Mocks\ValueObjects\StatusEnum;
use Tests\Mocks\ValueObjects\UserId;
use Tests\Unit\Executor\Mocks\UserSchema;

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
    ['\DateTimeImmutable|null', '2025-09-10T12:09:01+00:00', DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2025-09-10 12:09:01'),],

    ['string|null', 'my value', 'my value'],
    ['?string', 'my value', 'my value'],
    ['null|int|string', 'my value', 'my value'],
    ['null|int|string', null, null],
    ['string|int', 1, 1],
    ['array{int, string}', [1, 'my value'], [1, 'my value']],
    ['array{0: int,1: string}', [1, 'my value'], [1, 'my value']],
    ['array{id?: string, name: string}', ['id' => 'my id', 'name' => 'my name'], ['id' => 'my id', 'name' => 'my name']],
    ['array{id?: string, name: string}', ['name' => 'my name', 'other' => ''], ['name' => 'my name']],
    ['object{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object)['name' => 'my name']],
    ['object{id?: string, name: string}|null', ['name' => 'my name', 'other' => ''], (object)['name' => 'my name']],
    ['object{id?: string, name: string}|null', null, null],
    ['array<string, int>', ['my value' => 1], ['my value' => 1]],

    [UserSchema::class, (object)['username' => 'my name', 'age' => 1, "email" => "leo@me.test"], new UserSchema(1, 'leo@me.test', 'my name')],
    [UserSchema::class, ['username' => 'my name', 'age' => 1, "email" => "leo@me.test"], new UserSchema(1, 'leo@me.test', 'my name')],

    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['id' => 1, "reason" => "my value"],
        ['id' => 1, "reason" => "my value"],
    ],
    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['token' => "secret", "reason" => "my value"],
        ['token' => "secret", "reason" => "my value"],
    ],
    [
        '(object{id:positive-int}|object{token:string})&object{reason:string}',
        ['id' => 1, "reason" => "my value"],
        (object)['id' => 1, "reason" => "my value"],
    ],
    [
        '(object{id:positive-int}|object{token:string})&object{reason:string}',
        ['token' => "secret", "reason" => "my value"],
        (object)['token' => "secret", "reason" => "my value"],
    ],
    [
        'Pick<object{id:positive-int, name: string}, "id">',
        ['id' => 1, "name" => "my name"],
        (object)['id' => 1],
    ],

    [
        'Pick<array{id:positive-int, name: string}, "id">',
        ['id' => 1, "name" => "my name"],
        ['id' => 1],
    ],
    [
        'Omit<object{id:positive-int, name: string}, "id">',
        ['id' => 1, "name" => "my name"],
        (object)["name" => "my name"],
    ],
    [
        'Omit<array{id:positive-int, name: string}, "id">',
        ['id' => 1, "name" => "my name"],
        ["name" => "my name"],
    ],

    // Value objects
    [Email::class, 'ada@example.test', Email::fromStringValue('ada@example.test')],
    [UserId::class, 42, UserId::fromIntValue(42)],
    ['?\\' . Email::class, null, null],
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
    ['\DateTime|null', DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2025-09-10 12:09:01'), '2025-09-10T12:09:01+00:00'],

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
    ['array{id?: string, name: string}', ['id' => 'my id', 'name' => 'my name'], (object)['id' => 'my id', 'name' => 'my name']],
    ['array{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object)['name' => 'my name']],
    ['object{id?: string, name: string}', ['name' => 'my name', 'other' => ''], (object)['name' => 'my name']],
    ['object{id?: string, name: string}|null', ['name' => 'my name', 'other' => ''], (object)['name' => 'my name']],
    ['object{id?: string, name: string}|null', null, null],
    ['array<string>', ['my value', 'my other value'], ['my value', 'my other value']],
    ['array<string, int>', ['my value' => 1], (object)['my value' => 1]],

    [UserSchema::class, new UserSchema(1, 'leo@me.test', 'my name'), (object)['username' => 'my name', 'age' => 1]],

    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['id' => 1, "reason" => "my value"],
        (object)['id' => 1, "reason" => "my value"],
    ],
    [
        '(array{id:positive-int}|array{token:string})&array{reason:string}',
        ['token' => "secret", "reason" => "my value"],
        (object)['token' => "secret", "reason" => "my value"],
    ],
    [
        '(object{id:positive-int}|object{token:string})&object{reason:string}',
        ['id' => 1, "reason" => "my value"],
        (object)['id' => 1, "reason" => "my value"],
    ],
    [
        '(object{id:positive-int} | object{token:string}) & object{reason:string}',
        ['token' => "secret", "reason" => "my value"],
        (object)['token' => "secret", "reason" => "my value"],
    ],
    [
        'Pick<object{id:positive-int, name: string}, "id">',
        (object)['id' => 1, "name" => "my name"],
        (object)['id' => 1],
    ],

    [
        'Pick<array{id:positive-int, name: string}, "id">',
        ['id' => 1, "name" => "my name"],
        (object)['id' => 1],
    ],
    [
        'Omit<object{id:positive-int, name: string}, "id">',
        (object)['id' => 1, "name" => "my name"],
        (object)["name" => "my name"],
    ],
    [
        'Omit<array{id:positive-int, name: string, other: string}, "id">',
        ['id' => 1, "name" => "my name", "other" => 'string'],
        (object)["name" => "my name", "other" => 'string'],
    ],
    [
        'Omit< \\' . UserSchema::class . ', "age">',
        new UserSchema(12, 'email', 'username'),
        (object)["username" => "username"],
    ],
    [
        'Pick< \\' . UserSchema::class . ', "age">',
        new UserSchema(12, 'email', 'username'),
        (object)['age' => 12],
    ],

    // Value objects
    [Email::class, Email::fromStringValue('ada@example.test'), 'ada@example.test'],
    [UserId::class, UserId::fromIntValue(42), 42],
    ['?\\' . Email::class, null, null],
    // Serializes by backing value, NOT by the enum case name
    [StatusEnum::class, StatusEnum::INACTIVE, 'inactive'],
    ['\\' . Email::class . '[]', [Email::fromStringValue('a@b.test')], ['a@b.test']],
    [
        'array{id: \\' . UserId::class . ', email: \\' . Email::class . '}',
        ['id' => UserId::fromIntValue(7), 'email' => Email::fromStringValue('ada@example.test')],
        (object)['id' => 7, 'email' => 'ada@example.test'],
    ],
]);


test('serialization with partial failures', function () {
    /** @var Success $result */
    $result = executeSerialize('array{name: string|null, other: string}', [
        'name' => 123,
        'other' => 'my value',
    ]);

    expect($result)->toBeSuccess()
        ->and($result->value)->toEqual((object)[
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
            'validation.missing_property'
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

test('value object reports a throwing factory as a validation issue, not an internal error', function () {
    expect(executeParse(Email::class, 'not-an-email'))->toBeFailure('validation.invalid_type');
    expect(executeParse(UserId::class, 0))->toBeFailure('validation.invalid_type');
    expect(executeParse(UserId::class, -1))->toBeFailure('validation.invalid_type');

    $result = executeParse(Email::class, 'not-an-email');
    $messages = array_map(fn(Issue $issue) => $issue->messageOrLocalizationKey, $result->issues->allFlat());
    expect($messages)->not->toContain('internal_error');
});

test('the original exception is attached to the issue for debugging', function () {
    $result = executeParse(Email::class, 'not-an-email');
    $issue = $result->issues->allFlat()[0];

    expect($issue->messageOrLocalizationKey)->toBe('validation.invalid_type')
        ->and($issue->exception)->toBeInstanceOf(InvalidArgumentException::class)
        ->and($issue->exception->getMessage())->toBe('Invalid email: not-an-email');
});

test('an Error thrown by the factory is caught, not just an Exception', function () {
    // StatusEnum::fromStringValue() delegates to self::from(), which throws \ValueError.
    // \ValueError extends Error, NOT Exception, so catching Exception would let it escape.
    $result = executeParse(StatusEnum::class, 'not-a-case');

    expect($result)->toBeFailure('validation.invalid_type')
        ->and($result->issues->allFlat()[0]->exception)->toBeInstanceOf(ValueError::class);
});

test('a throwing accessor on the serialize path is an internal error, not a validation issue', function () {
    $result = executeSerialize(ExplodingValueObject::class, ExplodingValueObject::fromStringValue('x'));

    expect($result)->toBeFailure('internal_error')
        ->and($result->issues->allFlat()[0]->exception)->toBeInstanceOf(LogicException::class);
});

test('a throwing accessor never escapes the executor', function () {
    expect(fn() => executeSerialize(
        'array{a: \\' . ExplodingValueObject::class . '}',
        ['a' => ExplodingValueObject::fromStringValue('x')],
    ))->not->toThrow(LogicException::class);
});

test('a throwing accessor degrades to null at a nullable boundary', function () {
    /** @var Success $result */
    $result = executeSerialize(
        'array{a: ?\\' . ExplodingValueObject::class . '}',
        ['a' => ExplodingValueObject::fromStringValue('x')],
    );

    expect($result)->toBeSuccess()
        ->and($result->value)->toEqual((object)['a' => null])
        ->and($result->isPartial())->toBeTrue();
});

test('value objects nested in structs and lists hydrate correctly', function () {
    // Not in the 'parse success' dataset: that helper compares with toBe(), which is identity
    // based for objects nested inside an array.
    $struct = executeParse(
        'array{id: \\' . UserId::class . ', email: \\' . Email::class . '}',
        ['id' => 1, 'email' => 'ada@example.test'],
    );

    expect($struct)->toBeSuccess()
        ->and($struct->value)->toEqual([
            'email' => Email::fromStringValue('ada@example.test'),
            'id' => UserId::fromIntValue(1),
        ]);

    $list = executeParse('\\' . Email::class . '[]', ['a@b.test', 'c@d.test']);

    expect($list)->toBeSuccess()
        ->and($list->value)->toEqual([
            Email::fromStringValue('a@b.test'),
            Email::fromStringValue('c@d.test'),
        ]);
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
    'default ATOM format' => ['DateTimeString', '2025-09-10T12:09:01+00:00', '2025-09-10 12:09:01.000000 +00:00'],

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

test('the ATOM default does not accept a Z suffix', function (string $type, string $value) {
    // ATOM's P specifier renders UTC as +00:00, so a Z suffix no longer round trips.
    // Clients sending Date.toISOString() output need DateTimeString<'Y-m-d\TH:i:sp'>.
    expect(executeParse($type, $value))->toBeFailure('validation.invalid_type');
})->with([
    'utility type' => ['DateTimeString', '2025-09-10T12:09:01Z'],
    'class name' => ['\DateTimeImmutable', '2025-09-10T12:09:01Z'],
    'with milliseconds' => ['DateTimeString', '2025-09-10T12:09:01.000Z'],
]);

test('DateTimeString serializes a date back to its format', function (string $type, mixed $value, string $expected) {
    $result = executeSerialize($type, $value);

    expect($result)->toBeSuccess()->and($result->value)->toBe($expected);
})->with([
    'immutable' => ["DateTimeString<'Y-m-d'>", new DateTimeImmutable('2025-01-01 10:11:12'), '2025-01-01'],
    'mutable' => ["DateTimeString<'Y-m-d'>", new \DateTime('2025-01-01 10:11:12'), '2025-01-01'],
    'default ATOM format' => ['DateTimeString', new DateTimeImmutable('2025-09-10 12:09:01'), '2025-09-10T12:09:01+00:00'],
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
    ['DateTimeString', '2025-09-10T12:09:01+00:00'],
    ["DateTimeString<'Y-m-d'>", '2025-01-01'],
    ["DateTimeString<'d.m.Y H:i'>", '01.02.2025 08:30'],
    ["DateTimeString<'Y-m-d\\TH:i:sp'>", '2025-09-10T12:09:01Z'],
]);

test('value object issues are reported at the right field path', function () {
    $result = executeParse('array{email: \\' . Email::class . '}', ['email' => 'nope']);

    expect($result)->toBeFailureAt('email', 'validation.invalid_type');
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
    expect(executeSerialize('?\\' . Email::class, null))->toBeSuccess();
    expect(executeParse('?\\' . Email::class, null))->toBeSuccess();
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
        ->and($serialized->value)->toEqual((object)['email' => 'ada@example.test', 'ownerId' => 7]);
});

