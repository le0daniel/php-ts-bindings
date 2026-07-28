<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BackingType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Tests\Mocks\ValueObjects\AbstractValueObject;
use Tests\Mocks\ValueObjects\AmbiguousValueObject;
use Tests\Mocks\ValueObjects\CreateAccountInput;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\StatusEnum;
use Tests\Mocks\ValueObjects\UserId;

test('parses a string value object', function () {
    $node = new TypeParser()->parse(Email::class);

    expect($node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->className)->toBe(Email::class)
        ->and($node->backingType)->toBe(BackingType::STRING)
        ->and($node->brand)->toBe('email');

    compareToOptimizedAst($node);
    validateAst($node);
});

test('parses an int value object with an explicit brand name', function () {
    $node = new TypeParser()->parse(UserId::class);

    expect($node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->className)->toBe(UserId::class)
        ->and($node->backingType)->toBe(BackingType::INT)
        ->and($node->brand)->toBe('customerId');

    compareToOptimizedAst($node);
    validateAst($node);
});

test('the default brand name is lcfirst of the base class name', function (string $type, ?string $expected) {
    $node = new TypeParser()->parse($type);
    expect($node->brand)->toBe($expected);
})->with([
    'bare #[Brand] on Email' => [Email::class, 'email'],
    'explicit #[Brand(customerId)]' => [UserId::class, 'customerId'],
    'no attribute' => [Slug::class, null],
]);

test('a value object without the Brand attribute is not branded', function () {
    $node = new TypeParser()->parse(Slug::class);

    expect($node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->brand)->toBeNull();

    compareToOptimizedAst($node);
});

test('a value object may also implement Stringable without colliding', function () {
    // Slug declares its own toString() and __toString(); the interface uses toStringValue().
    $node = new TypeParser()->parse(Slug::class);
    expect($node)->toBeInstanceOf(ValueObjectNode::class);

    $result = executeSerialize($node, Slug::fromStringValue('my-slug'));
    expect($result)->toBeSuccess()
        ->and($result->value)->toBe('my-slug');
});

test('resolves value objects through the namespace of the parsing context', function () {
    $node = new TypeParser()->parse('Email', new ParsingContext('Tests\\Mocks\\ValueObjects'));

    expect($node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->className)->toBe(Email::class);
});

test('resolves value objects through a use-statement alias', function () {
    $node = new TypeParser()->parse('Mail', new ParsingContext('Some\\Space', ['Mail' => Email::class]));

    expect($node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->className)->toBe(Email::class);
});

test('value objects compose with the rest of the grammar', function (string $type, string $expected) {
    $node = new TypeParser()->parse($type);

    expect($node)->toBeInstanceOf($expected);

    compareToOptimizedAst($node);
    validateAst($node);
})->with([
    'nullable' => ['?\\' . Email::class, UnionNode::class],
    'array shorthand' => ['\\' . Email::class . '[]', ListNode::class],
    'list generic' => ['list<\\' . Email::class . '>', ListNode::class],
    'union' => ['\\' . Email::class . '|null', UnionNode::class],
    'record' => ['array<string, \\' . UserId::class . '>', RecordNode::class],
    'struct' => ['array{id: \\' . UserId::class . ', email: \\' . Email::class . '}', StructNode::class],
    'object struct' => ['object{id: \\' . UserId::class . '}', StructNode::class],
]);

test('rejects a class implementing both value object interfaces', function () {
    expect(fn() => new TypeParser()->parse(AmbiguousValueObject::class))
        ->toThrow('must implement either StringValueObject or IntValueObject, not both');
});

test('rejects an abstract value object', function () {
    expect(fn() => new TypeParser()->parse(AbstractValueObject::class))
        ->toThrow('must be instantiable');
});

test('the value object interface wins over the enum consumer', function () {
    $node = new TypeParser()->parse(StatusEnum::class);

    expect($node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->backingType)->toBe(BackingType::STRING);

    compareToOptimizedAst($node);
});

test('a value object is never treated as a castable object', function () {
    $parser = new TypeParser(TypeParser::defaultConsumers(allowAllObjectCasting: true));

    expect($parser->parse(Slug::class))->toBeInstanceOf(ValueObjectNode::class);
});

test('value objects emit their backing primitive when brands are disabled', function () {
    $node = new TypeParser()->parse(Email::class);
    $options = new Options(ignoreBrandedTypes: true);

    expect(typescriptFor($node, IO::OUTPUT, $options)->type)->toBe('string');
    expect(typescriptFor($node, IO::INPUT, $options)->type)->toBe('string');
});

test('value objects emit branded types when brands are enabled', function (string $type, string $alias, string $expected) {
    $node = new TypeParser()->parse($type);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        // The use site carries the alias, the definition travels in the registry, and inlining the
        // one into the other reproduces the full branded type.
        expect(typescriptFor($node, $io)->type)->toBe($alias);
        expect(typescriptFor($node, $io)->toStandaloneType())->toBe($expected);
    }
})->with([
    'string vo' => [Email::class, 'Email', 'string & Brand<"email">'],
    'int vo renamed' => [UserId::class, 'CustomerId', 'number & Brand<"customerId">'],
    'unbranded vo' => [Slug::class, 'string', 'string'],
]);

test('a castable class carrying value object properties', function () {
    $node = new TypeParser()->parse(CreateAccountInput::class);

    expect($node)->toBeInstanceOf(CustomCastingNode::class);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        expect(typescriptFor($node, $io)->type)->toBe('{email:Email;ownerId:CustomerId;}');
        expect(typescriptFor($node, $io, new Options(ignoreBrandedTypes: true))->type)
            ->toBe('{email:string;ownerId:number;}');
    }

    compareToOptimizedAst($node);
});
