<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\NamedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ReferencedNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeRegistry;
use Le0daniel\PhpTsBindings\Typescript\Data\TypeScript;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
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
    IO                   $io = IO::INPUT,
    Options              $options = new Options(),
): TypeScript
{
    $node = is_string($type) ? new TypeParser()->parse($type) : $type;
    return new TypescriptGenerator()->toTypescript($node, $io, $options);
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
    'numeric collapses int|float to one number' => ['numeric', 'number'],
    'scalar dedupes int|float' => ['scalar', 'number|boolean|string'],
]);

test('emits literal types', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'string literal' => ["'foo'", '"foo"'],
    'int literal' => ['123', '123'],
    'float literal' => ['1.5', '1.5'],
    'true' => ['true', 'true'],
    'false' => ['false', 'false'],
    'enum case literal uses the case name' => ['\\' . ResultEnum::class . '::SUCCESS', '"SUCCESS"'],
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
    expect(typescriptOfBoth('\\' . ResultEnum::class))->toBe('"SUCCESS"|"FAILURE"');
});

test('throws for an enum without cases', function () {
    expect(fn() => typescriptOf(new EnumNode(EmptyEnum::class)))
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
    'array shorthand' => ['string[]', 'Array<string>'],
    'int keyed array' => ['array<int, string>', 'Array<string>'],
    'bare array' => ['array', 'Array<unknown>'],
    'record' => ['array<string, int>', 'Record<string,number>'],
    'tuple' => ['array{string, int}', '[string,number]'],
    'explicitly keyed tuple' => ['array{0: string, 1: int}', '[string,number]'],
]);

test('emits unions and intersections with correct precedence', function (string $type, string $expected) {
    expect(typescriptOfBoth($type))->toBe($expected);
})->with([
    'union' => ['array{name: string}|string', '{name:string;}|string'],
    'nullable' => ['?string', 'null|string'],
    'union dedupes rendered members' => ['int|string|int', 'number|string'],
    'union dedupes equal literals' => ["'a'|'b'|'a'", '"a"|"b"'],
    'union member that is an intersection is parenthesised' => [
        'array{a: string}|(array{b: int}&array{c: bool})',
        '{a:string;}|({b:number;}&{c:boolean;})',
    ],
    'intersection member that is a union is parenthesised' => [
        '(array{id: positive-int}|array{token: string})&array{reason: string}',
        '({id:number;}|{token:string;})&{reason:string;}',
    ],
]);

test('references a branded alias at the use site and returns its definition', function (
    string $type,
    string $expectedType,
    array  $expectedBrands,
) {
    $result = typescriptOf($type);

    expect($result->type)->toBe($expectedType)
        ->and($result->registry->toArray())->toBe($expectedBrands);
})->with([
    'string value object' => ['\\' . Email::class, 'Email', ['Email' => 'string & Brand<"email">']],
    'int value object aliased by its explicit brand' => [
        '\\' . UserId::class,
        'CustomerId',
        ['CustomerId' => 'number & Brand<"customerId">'],
    ],
    'unbranded value object stays a plain string' => ['\\' . Slug::class, 'string', []],
    'BrandedString' => ["BrandedString<'token'>", 'Token', ['Token' => 'string & Brand<"token">']],
    'BrandedInt' => ["BrandedInt<'wow'>", 'Wow', ['Wow' => 'number & Brand<"wow">']],
]);

test('collects brands from any depth of the tree', function (string $type, string $expectedType, array $expectedBrands) {
    $result = typescriptOf($type);

    expect($result->type)->toBe($expectedType)
        ->and($result->registry->toArray())->toBe($expectedBrands);
})->with([
    'inside a struct' => [
        '\\' . CreateAccountInput::class,
        '{email:Email;ownerId:CustomerId;}',
        ['CustomerId' => 'number & Brand<"customerId">', 'Email' => 'string & Brand<"email">'],
    ],
    'inside a list' => [
        'list<\\' . Email::class . '>',
        'Array<Email>',
        ['Email' => 'string & Brand<"email">'],
    ],
    'inside a union' => [
        '?\\' . Email::class,
        'null|Email',
        ['Email' => 'string & Brand<"email">'],
    ],
    'inside a record' => [
        'array<string, \\' . UserId::class . '>',
        'Record<string,CustomerId>',
        ['CustomerId' => 'number & Brand<"customerId">'],
    ],
    'the same brand used twice is collected once' => [
        "array{a: BrandedString<'token'>, b: BrandedString<'token'>}",
        '{a:Token;b:Token;}',
        ['Token' => 'string & Brand<"token">'],
    ],
]);

test('returns branded types sorted by alias', function () {
    $result = typescriptOf("array{z: BrandedString<'zulu'>, a: BrandedString<'alpha'>, m: BrandedString<'mike'>}");

    expect(array_keys($result->registry->toArray()))->toBe(['Alpha', 'Mike', 'Zulu'])
        ->and($result->type)->toBe('{z:Zulu;a:Alpha;m:Mike;}');
});

test('throws when one brand resolves to two different definitions', function () {
    expect(fn() => typescriptOf("array{a: BrandedString<'token'>, b: BrandedInt<'token'>}"))
        ->toThrow(UnsupportedTypeException::class, 'Token');
});

test('toStandaloneType inlines every branded alias', function () {
    $result = typescriptOf('\\' . CreateAccountInput::class);

    expect($result->toStandaloneType())
        ->toBe('{email:string & Brand<"email">;ownerId:number & Brand<"customerId">;}');
});

test('toStandaloneType does not let one alias corrupt another that starts with it', function () {
    $result = typescriptOf("array{a: BrandedString<'user'>, b: BrandedInt<'userId'>}");

    expect($result->type)->toBe('{a:User;b:UserId;}')
        ->and($result->toStandaloneType())
        ->toBe('{a:string & Brand<"user">;b:number & Brand<"userId">;}');
});

test('toStandaloneType equals the type when nothing is branded', function () {
    $result = typescriptOf('array{name: string, tags: list<string>}');

    expect($result->registry->isEmpty())->toBeTrue()
        ->and($result->toStandaloneType())->toBe($result->type);
});

test('reads a collected alias back out of the registry', function () {
    $registry = typescriptOf('\\' . CreateAccountInput::class)->registry;

    expect($registry->isEmpty())->toBeFalse()
        ->and($registry->has('Email'))->toBeTrue()
        ->and($registry->get('Email'))->toBe('string & Brand<"email">')
        ->and($registry->has('Nope'))->toBeFalse();
});

test('generates against a registry passed in without mutating it', function () {
    $shared = new TypeRegistry(['Existing' => 'string & Brand<"existing">']);

    $result = typescriptOf('\\' . Email::class, IO::INPUT, new Options(registry: $shared));

    expect($result->registry->toArray())->toBe([
        'Email' => 'string & Brand<"email">',
        'Existing' => 'string & Brand<"existing">',
    ])
        ->and($result->registry)->not->toBe($shared)
        ->and($shared->toArray())->toBe(['Existing' => 'string & Brand<"existing">']);
});

test('throws when the incoming registry already binds an alias to something else', function () {
    $shared = new TypeRegistry(['Email' => 'number & Brand<"email">']);

    expect(fn() => typescriptOf('\\' . Email::class, IO::INPUT, new Options(registry: $shared)))
        ->toThrow(UnsupportedTypeException::class, 'Email');
});

test('filters struct properties by direction', function () {
    $type = '\\' . UserSchema::class;

    expect(typescriptOf($type, IO::INPUT)->type)->toBe('{age:number;email:string;username:string;}')
        ->and(typescriptOf($type, IO::OUTPUT)->type)->toBe('{age:number;username:string;}');
});

test('emits an empty object when no property survives the direction filter', function () {
    $node = new StructNode(StructPhpType::OBJECT, [
        new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false, PropertyType::OUTPUT),
    ]);

    expect(typescriptOf($node, IO::INPUT)->type)->toBe('{}')
        ->and(typescriptOf($node, IO::OUTPUT)->type)->toBe('{name:string;}');
});

test('throws for an uncastable class on input but emits it on output', function (string $class, string $output) {
    $type = '\\' . $class;

    expect(fn() => typescriptOf($type, IO::INPUT))
        ->toThrow(UnsupportedTypeException::class, $class);

    expect(typescriptOf($type, IO::OUTPUT)->type)->toBe($output);
})->with([
    'class without #[Castable]' => [UncastableClass::class, '{email:string;name:string;}'],
    'abstract class' => [SomeAbstractClass::class, '{id:number;email:string;}'],
    'interface' => [SomeFileInterface::class, '{id:number;url:string;}'],
    'readonly output fields' => [ReadonlyOutputFields::class, '{name:string;email:string;}'],
]);

test('throws for nodes it cannot represent', function (NodeInterface $node) {
    expect(fn() => typescriptOf($node))->toThrow(UnsupportedTypeException::class);
})->with([
    'NamedNode' => [new NamedNode(new BuiltInNode(BuiltInType::STRING), 'Legacy')],
    'ReferencedNode' => [new ReferencedNode('#leaf_abc', 'string', 'registry')],
    'unknown node implementation' => [new class implements NodeInterface {
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

test('pretty prints nested struct literals', function () {
    $result = typescriptOf(
        'array{items: list<array{id: string}>, total: int}',
        IO::INPUT,
        new Options(pretty: true),
    );

    expect($result->type)->toBe(<<<TS
    {
        items: Array<{
            id: string;
        }>;
        total: number;
    }
    TS);
});

test('pretty printing spaces out the remaining separators', function (string $type, string $expected) {
    expect(typescriptOf($type, IO::INPUT, new Options(pretty: true))->type)->toBe($expected);
})->with([
    'union' => ['int|string', 'number | string'],
    'intersection' => ['array{a: int}&array{b: string}', "{\n    a: number;\n} & {\n    b: string;\n}"],
    'tuple' => ['array{string, int}', '[string, number]'],
    'record' => ['array<string, int>', 'Record<string, number>'],
    'list' => ['list<string>', 'Array<string>'],
    'optional key' => ['array{name?: string}', "{\n    name?: string;\n}"],
    'quoted key' => ["array{'a b': string}", "{\n    \"a b\": string;\n}"],
]);

test('pretty printing keeps an empty object on one line', function () {
    $node = new StructNode(StructPhpType::OBJECT, [
        new PropertyNode('name', new BuiltInNode(BuiltInType::STRING), false, PropertyType::OUTPUT),
    ]);

    expect(typescriptOf($node, IO::INPUT, new Options(pretty: true))->type)->toBe('{}');
});

test('pretty printing still references branded aliases', function () {
    $result = typescriptOf('\\' . CreateAccountInput::class, IO::INPUT, new Options(pretty: true));

    expect($result->type)->toBe(<<<TS
    {
        email: Email;
        ownerId: CustomerId;
    }
    TS)
        ->and($result->registry->toArray())->toBe([
            'CustomerId' => 'number & Brand<"customerId">',
            'Email' => 'string & Brand<"email">',
        ]);
});
