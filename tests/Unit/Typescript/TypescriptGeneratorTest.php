<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ReferencedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Mocks\ResultEnum;
use Tests\Mocks\ValueObjects\CreateAccountInput;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\UserId;
use Tests\Unit\Executor\Mocks\UserSchema;
use Tests\Unit\Parser\Data\Stubs\ReadonlyOutputFields;
use Tests\Unit\Parser\Data\Stubs\SomeAbstractClass;
use Tests\Unit\Parser\Data\Stubs\SomeFileInterface;
use Tests\Unit\Parser\Data\Stubs\UncastableClass;
use Tests\Unit\Typescript\Stubs\EmptyEnum;

function typescriptOf(
    string|NodeInterface $type,
    IO $io = IO::INPUT,
    ?AliasRegistry $sharedRegistry = null,
): Typescript {
    $node = is_string($type) ? new TypeParser()->parse($type) : $type;

    return new TypescriptGenerator()->toTypescript($node, $io, $sharedRegistry);
}

/**
 * Emitting the same node for both directions must not differ unless the schema says so.
 */
function typescriptOfBoth(string|NodeInterface $type): string
{
    $input = typescriptOf($type, IO::INPUT);
    $output = typescriptOf($type, IO::OUTPUT);
    expect($input->type)->toBe($output->type)
        ->and($input->registry->toArray())->toBe($output->registry->toArray());

    return $input->type;
}

test('emits built in scalar types', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'string' => ['string', 'string'],
    'int' => ['int', 'number'],
    'float' => ['float', 'number'],
    'bool' => ['bool', 'boolean'],
    'null' => ['null', 'null'],
    'mixed' => ['mixed', 'unknown'],
]);

test('emits constrained and aliased built in types', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'positive-int is a constrained int' => ['positive-int', 'number'],
    'non-empty-string is a constrained string' => ['non-empty-string', 'string'],
    'numeric collapses int|float to one number' => ['numeric', '(number)'],
    'scalar dedupes int|float' => ['scalar', '(number|boolean|string)'],
]);

test('emits literal types', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'string literal' => ["'foo'", '"foo"'],
    'int literal' => ['123', '123'],
    'float literal' => ['1.5', '1.5'],
    'true' => ['true', 'true'],
    'false' => ['false', 'false'],
    'enum case literal uses the case name' => ['\\'.ResultEnum::class.'::SUCCESS', '"SUCCESS"'],
]);

test('escapes string literals for typescript', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'double quotes' => ["'he said \"hi\"'", '"he said \\"hi\\""'],
    'backslash' => ["'back\\\\slash'", '"back\\\\slash"'],
    'unicode' => ["'héllo'", '"h\\u00e9llo"'],
    'empty string' => ["''", '""'],
]);

test('emits an enum as a union of its case names', function () {
    expect(typescriptOfBoth('\\'.ResultEnum::class))->toBe('("SUCCESS"|"FAILURE")');
});

test('throws for an enum without cases', function () {
    expect(fn () => typescriptOf(new EnumNode(EmptyEnum::class)))
        ->toThrow(UnsupportedTypeException::class, 'declares no cases');
});

test('emits every date time flavour as string', function (string $type) {
    expect(typescriptOfBoth($type))->toBe('string');
})->with([
    'DateTimeString' => ['DateTimeString'],
    'DateTimeString with format' => ["DateTimeString<'Y-m-d'>"],
    'DateTimeImmutable' => ['\\DateTimeImmutable'],
]);

test('emits struct types', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'array shape' => ['array{name: string}', '{name:string;}'],
    'optional key' => ['array{name?: string}', '{name?:string;}'],
    'object shape renders the same as an array shape' => ['object{id: int}', '{id:number;}'],
    'quoted key' => ["array{'key something else': string}", '{"key something else":string;}'],
    'optional quoted key' => ["array{'key something else'?: string}", '{"key something else"?:string;}'],
    'key needing escaping' => ["array{'quote\"key': string}", '{"quote\\"key":string;}'],
    'nested struct' => ['array{a: array{b: string}}', '{a:{b:string;};}'],
]);

test('emits collection types', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'list' => ['list<string>', 'Array<string>'],
    'non empty list' => ['non-empty-list<string>', 'Array<string>'],
    'array shorthand' => ['string[]', 'Array<string>'],
    'grouped array shorthand' => ['(string|int)[]', 'Array<(string|number)>'],
    'record' => ['array<string, int>', 'Record<string,number>'],

    // A JSON object key is a string, so an int keyed array is Record<string, V>. Record<number, V>
    // would read well at a call site and then lie about what Object.keys() hands back.
    'int keyed array' => ['array<int, string>', 'Record<string,string>'],
    'implicit array-key' => ['array<string>', 'Record<string,string>'],
    'non empty array' => ['non-empty-array<int, string>', 'Record<string,string>'],

    // A refinement on the key is proven server side and has no shape a key type could carry.
    'refined string key' => ['array<non-empty-string, int>', 'Record<string,number>'],
    'refined int key' => ['array<positive-int, int>', 'Record<string,number>'],

    // Only a closed key set lets TypeScript say more than `string`, and there Partial carries the
    // difference: Record<'a'|'b', V> demands both keys, a PHP array keyed by 'a'|'b' promises none.
    'literal key union' => ["array<'one'|'two', string>", 'Partial<Record<"one"|"two",string>>'],
    'single literal key' => ["array<'only', string>", 'Partial<Record<"only",string>>'],
    'int literal key union' => ['array<1|2, string>', 'Partial<Record<"1"|"2",string>>'],
    'literal key union dedupes' => ["array<'a'|'b'|'a', string>", 'Partial<Record<"a"|"b",string>>'],

    'record of lists' => ['array<string, list<string>>', 'Record<string,Array<string>>'],
    'record of records' => ['array<int, array<int, string>>', 'Record<string,Record<string,string>>'],
    'list of records' => ['list<array<int, string>>', 'Array<Record<string,string>>'],
    'tuple' => ['array{string, int}', '[string,number]'],
    'explicitly keyed tuple' => ['array{0: string, 1: int}', '[string,number]'],
]);

test('emits unions and intersections fully parenthesised', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'union' => ['array{name: string}|string', '({name:string;}|string)'],
    'nullable' => ['?string', '(null|string)'],
    'union dedupes rendered members' => ['int|string|int', '(number|string)'],
    'union dedupes equal literals' => ["'a'|'b'|'a'", '("a"|"b")'],
    'union member that is an intersection is parenthesised' => [
        'array{a: string}|(array{b: int}&array{c: bool})',
        '({a:string;}|({b:number;}&{c:boolean;}))',
    ],
    'intersection member that is a union is parenthesised' => [
        '(array{id: positive-int}|array{token: string})&array{reason: string}',
        '(({id:number;}|{token:string;})&{reason:string;})',
    ],
]);

test('an attribute brand renders inline and declares no alias', function (string $type, string $expectedType) {
    $result = typescriptOf($type);

    expect($result->type)->toBe($expectedType)
        ->and($result->registry->isEmpty())->toBeTrue();
})->with([
    'string value object' => ['\\'.Email::class, '(string & Brand<"email">)'],
    'int value object with an explicit brand' => ['\\'.UserId::class, '(number & Brand<"customerId">)'],
    'unbranded value object stays a plain string' => ['\\'.Slug::class, 'string'],
    'inside a struct' => [
        '\\'.CreateAccountInput::class,
        '{email:(string & Brand<"email">);ownerId:(number & Brand<"customerId">);}',
    ],
    'inside a list' => ['list<\\'.Email::class.'>', 'Array<(string & Brand<"email">)>'],
    'inside a union' => ['?\\'.Email::class, '(null|(string & Brand<"email">))'],
    'inside a record' => [
        'array<string, \\'.UserId::class.'>',
        'Record<string,(number & Brand<"customerId">)>',
    ],
]);

test('a brand on a record key is dropped and declares no alias', function (string $type, string $expectedType) {
    // The key travels as a property name. A branded key type would force the client to cast every
    // Object.keys() result before it could index with one, and since the key is never emitted the
    // alias a BrandedString would otherwise register is never collected either.
    $result = typescriptOf($type);

    expect($result->type)->toBe($expectedType)
        ->and($result->registry->isEmpty())->toBeTrue();
})->with([
    'branded string key' => ["array<BrandedString<'k'>, int>", 'Record<string,number>'],
    'branded int key' => ["array<BrandedInt<'k'>, string>", 'Record<string,string>'],
]);

test('the BrandedString and BrandedInt utilities keep their implicit alias', function (
    string $type,
    string $expectedType,
    array $expectedAliases,
) {
    $result = typescriptOf($type);

    expect($result->type)->toBe($expectedType)
        ->and($result->registry->toArray())->toBe($expectedAliases);
})->with([
    'BrandedString' => ["BrandedString<'token'>", 'Token', ['Token' => '(string & Brand<"token">)']],
    'BrandedInt' => ["BrandedInt<'wow'>", 'Wow', ['Wow' => '(number & Brand<"wow">)']],
    'the same alias used twice is collected once' => [
        "array{a: BrandedString<'token'>, b: BrandedString<'token'>}",
        '{a:Token;b:Token;}',
        ['Token' => '(string & Brand<"token">)'],
    ],
]);

test('returns branded types sorted by alias', function () {
    $result = typescriptOf("array{z: BrandedString<'zulu'>, a: BrandedString<'alpha'>, m: BrandedString<'mike'>}");

    expect(array_keys($result->registry->toArray()))->toBe(['Alpha', 'Mike', 'Zulu'])
        ->and($result->type)->toBe('{a:Alpha;m:Mike;z:Zulu;}');
});

test('throws when one brand resolves to two different definitions', function () {
    expect(fn () => typescriptOf("array{a: BrandedString<'token'>, b: BrandedInt<'token'>}"))
        ->toThrow(UnsupportedTypeException::class, 'Token');
});

test('reads a collected alias back out of the registry', function () {
    $registry = typescriptOf("BrandedString<'email'>")->registry;

    expect($registry->isEmpty())->toBeFalse()
        ->and($registry->has('Email'))->toBeTrue()
        ->and($registry->get('Email'))->toBe('(string & Brand<"email">)')
        ->and($registry->has('Nope'))->toBeFalse();
});

test('registers into the passed registry but returns only what the emission needs', function () {
    $shared = new AliasRegistry(['Existing' => '(string & Brand<"existing">)']);

    $result = typescriptOf("BrandedString<'email'>", IO::INPUT, $shared);

    expect($shared->toArray())->toBe([
        'Email' => '(string & Brand<"email">)',
        'Existing' => '(string & Brand<"existing">)',
    ])
        ->and($result->registry->toArray())->toBe(['Email' => '(string & Brand<"email">)'])
        ->and($result->registry->usedAliases())->toBe(['Email']);
});

test('one shared registry accumulates aliases across emissions', function () {
    $shared = new AliasRegistry();

    $first = typescriptOf("BrandedString<'email'>", IO::INPUT, $shared);
    $second = typescriptOf("BrandedInt<'customerId'>", IO::INPUT, $shared);

    expect($shared->toArray())->toBe([
        'CustomerId' => '(number & Brand<"customerId">)',
        'Email' => '(string & Brand<"email">)',
    ])
        ->and($first->registry->toArray())->toBe(['Email' => '(string & Brand<"email">)'])
        ->and($second->registry->toArray())->toBe(['CustomerId' => '(number & Brand<"customerId">)']);
});

test('throws when the incoming registry already binds an alias to something else', function () {
    $shared = new AliasRegistry(['Email' => '(number & Brand<"email">)']);

    expect(fn () => typescriptOf("BrandedString<'email'>", IO::INPUT, $shared))
        ->toThrow(UnsupportedTypeException::class, 'Email');
});

test('filters struct properties by direction', function () {
    $type = '\\'.UserSchema::class;

    expect(typescriptOf($type, IO::INPUT)->type)->toBe('{age:number;email:string;username:string;}')
        ->and(typescriptOf($type, IO::OUTPUT)->type)->toBe('{age:number;username:string;}');
});

test('emits an empty object when no property survives the direction filter', function () {
    $node = new StructNode(StructPhpType::OBJECT, [
        new PropertyNode('name', new StringNode(), false, PropertyType::OUTPUT),
    ]);

    expect(typescriptOf($node, IO::INPUT)->type)->toBe('{}')
        ->and(typescriptOf($node, IO::OUTPUT)->type)->toBe('{name:string;}');
});

test('throws for an uncastable class on input but emits it on output', function (string $class, string $output) {
    $type = '\\'.$class;

    expect(fn () => typescriptOf($type, IO::INPUT))
        ->toThrow(UnsupportedTypeException::class, $class);

    expect(typescriptOf($type, IO::OUTPUT)->type)->toBe($output);
})->with([
    'class without #[Castable]' => [UncastableClass::class, '{email:string;name:string;}'],
    'abstract class' => [SomeAbstractClass::class, '{email:string;id:number;}'],
    'interface' => [SomeFileInterface::class, '{id:number;url:string;}'],
    'readonly output fields' => [ReadonlyOutputFields::class, '{email:string;name:string;}'],
]);

test('throws for nodes it cannot represent', function (NodeInterface $node) {
    expect(fn () => typescriptOf($node))->toThrow(UnsupportedTypeException::class);
})->with([
    'ReferencedNode' => [new ReferencedNode('#leaf_abc', 'string', 'registry')],
    'unknown node implementation' => [new class () implements NodeInterface {
        public function __toString(): string
        {
            return 'unknown';
        }

        public function exportPhpCode(): string
        {
            return '';
        }
    }],
]);
