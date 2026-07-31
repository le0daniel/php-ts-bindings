<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BackingType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Tests\Mocks\ValueObjects\AbstractValueObject;
use Tests\Mocks\ValueObjects\AmbiguousValueObject;
use Tests\Mocks\ValueObjects\CreateAccountInput;
use Tests\Mocks\ValueObjects\Email;
use Tests\Mocks\ValueObjects\Slug;
use Tests\Mocks\ValueObjects\StatusEnum;
use Tests\Mocks\ValueObjects\UserId;

test('parses a string value object into a brand-carrying metadata wrapper', function () {
    $node = new TypeParser()->parse(Email::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe('email')
        ->and($node->name)->toBeNull()
        ->and($node->node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->node->className)->toBe(Email::class)
        ->and($node->node->backingType)->toBe(BackingType::STRING);

    compareToOptimizedAst($node);
    validateAst($node);
});

test('parses an int value object with an explicit brand name', function () {
    $node = new TypeParser()->parse(UserId::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe('customerId')
        ->and($node->node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->node->className)->toBe(UserId::class)
        ->and($node->node->backingType)->toBe(BackingType::INT);

    compareToOptimizedAst($node);
    validateAst($node);
});

test('the default brand name is lcfirst of the base class name', function (string $type, ?string $expected) {
    $node = new TypeParser()->parse($type);
    expect($node instanceof MetadataNode ? $node->brand : null)->toBe($expected);
})->with([
    'bare #[Brand] on Email' => [Email::class, 'email'],
    'explicit #[Brand(customerId)]' => [UserId::class, 'customerId'],
    'no attribute' => [Slug::class, null],
]);

test('a value object without codegen attributes stays a bare node', function () {
    $node = new TypeParser()->parse(Slug::class);

    expect($node)->toBeInstanceOf(ValueObjectNode::class);

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
    $node = new TypeParser()->parse('Email', new ParsingScope('Tests\\Mocks\\ValueObjects'));

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->node->className)->toBe(Email::class);
});

test('resolves value objects through a use-statement alias', function () {
    $node = new TypeParser()->parse('Mail', new ParsingScope('Some\\Space', ['Mail' => Email::class]));

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->node->className)->toBe(Email::class);
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

test('value objects emit their brand inline in both directions', function (string $type, string $expected) {
    $node = new TypeParser()->parse($type);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $result = typescriptFor($node, $io);
        expect($result->type)->toBe($expected)
            ->and($result->registry->isEmpty())->toBeTrue();
    }
})->with([
    'string vo' => [Email::class, '(string & Brand<"email">)'],
    'int vo renamed' => [UserId::class, '(number & Brand<"customerId">)'],
    'unbranded vo' => [Slug::class, 'string'],
]);

test('a castable class carrying value object properties', function () {
    $node = new TypeParser()->parse(CreateAccountInput::class);

    expect($node)->toBeInstanceOf(CustomCastingNode::class);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        expect(typescriptFor($node, $io)->type)
            ->toBe('{email:(string & Brand<"email">);ownerId:(number & Brand<"customerId">);}');
    }

    compareToOptimizedAst($node);
});

test('branded value objects execute like their bare counterpart', function () {
    $parsed = executeParse(Email::class, 'user@example.com');
    expect($parsed)->toBeSuccess()
        ->and($parsed->value)->toBeInstanceOf(Email::class);

    $serialized = executeSerialize(Email::class, Email::fromStringValue('user@example.com'));
    expect($serialized)->toBeSuccess()
        ->and($serialized->value)->toBe('user@example.com');
});
