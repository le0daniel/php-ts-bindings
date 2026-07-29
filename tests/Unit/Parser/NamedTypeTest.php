<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Tests\Mocks\Named\Customer;
use Tests\Mocks\Named\InvalidlyBranded;
use Tests\Mocks\Named\InvalidlyNamed;
use Tests\Mocks\Named\NamedValueObject;
use Tests\Mocks\Named\Order;
use Tests\Mocks\Named\OrderStatus;
use Tests\Mocks\Named\RenamedThing;

test('resolves #[Named] on a class to the base name and output direction by default', function () {
    $node = new TypeParser()->parse(Customer::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->name)->toBe('Customer')
        ->and($node->name?->io)->toBe(IO::OUTPUT)
        ->and($node->brand)->toBeNull()
        ->and($node->node)->toBeInstanceOf(CustomCastingNode::class);

    compareToOptimizedAst($node);
    validateAst($node);
});

test('an explicit name wins over the base name', function () {
    $node = new TypeParser()->parse(RenamedThing::class);

    expect($node->name?->name)->toBe('CustomThing');
});

test('a class without codegen attributes carries no metadata wrapper', function () {
    $node = new TypeParser()->parse(\Tests\Mocks\ValueObjects\CreateAccountInput::class);

    expect($node)->toBeInstanceOf(CustomCastingNode::class);
});

test('#[Named] on an enum defaults to IO::BOTH, its shape is identical in both directions', function () {
    $node = new TypeParser()->parse(OrderStatus::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->name)->toBe('OrderStatus')
        ->and($node->name?->io)->toBe(IO::BOTH)
        ->and($node->node)->toBeInstanceOf(EnumNode::class);

    compareToOptimizedAst($node);
});

test('a value object can combine #[Brand] and #[Named]', function () {
    $node = new TypeParser()->parse(NamedValueObject::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->name)->toBe('AccountId')
        ->and($node->name?->io)->toBe(IO::BOTH)
        ->and($node->brand)->toBe('accountId')
        ->and($node->node)->toBeInstanceOf(ValueObjectNode::class);
});

test('metadata is transparent in the string form and eliminated from the optimized AST', function () {
    $node = new TypeParser()->parse(Order::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and((string)$node)->toBe((string)$node->node)
        ->and($node->exportPhpCode())->not->toContain('MetadataNode');

    $optimizedCode = new ASTOptimizer()->generateOptimizedCode(['node' => $node]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    expect($optimizedCode)->not->toContain('MetadataNode')
        ->and($registry->get('node'))->not->toBeInstanceOf(MetadataNode::class)
        ->and((string)$registry->get('node'))->toBe((string)$node);
});

test('rejects a name that is not a valid TypeScript identifier', function () {
    expect(fn() => new TypeParser()->parse(InvalidlyNamed::class))
        ->toThrow(InvalidStringLiteralException::class, 'not a valid TypeScript identifier');
});

test('rejects a brand tag that is not a valid TypeScript identifier', function () {
    expect(fn() => new TypeParser()->parse(InvalidlyBranded::class))
        ->toThrow(InvalidStringLiteralException::class, 'not a valid TypeScript identifier');
});
