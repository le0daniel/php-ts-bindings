<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Helpers\AstValidator;
use Le0daniel\PhpTsBindings\Parser\Helpers\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\EnumNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\ValueObjectNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Tests\Mocks\Named\ArticleResource;
use Tests\Mocks\Named\AsymmetricNamed;
use Tests\Mocks\Named\Customer;
use Tests\Mocks\Named\InvalidlyBranded;
use Tests\Mocks\Named\InvalidlyNamed;
use Tests\Mocks\Named\NamedValueObject;
use Tests\Mocks\Named\Order;
use Tests\Mocks\Named\OrderStatus;
use Tests\Mocks\Named\PerDirectionNamed;
use Tests\Mocks\Named\RenamedThing;
use Tests\Mocks\ValueObjects\CreateAccountInput;
use Tests\Mocks\ValueObjects\Inherited\AccountId;
use Tests\Mocks\ValueObjects\Inherited\AmbiguousId;
use Tests\Mocks\ValueObjects\Inherited\BadClosureId;
use Tests\Mocks\ValueObjects\Inherited\BaseId;
use Tests\Mocks\ValueObjects\Inherited\BrandId;
use Tests\Mocks\ValueObjects\Inherited\ChildId;
use Tests\Mocks\ValueObjects\Inherited\ComputedLocally;
use Tests\Mocks\ValueObjects\Inherited\DeepId;
use Tests\Mocks\ValueObjects\Inherited\DisambiguatedId;
use Tests\Mocks\ValueObjects\Inherited\GrandChildId;
use Tests\Mocks\ValueObjects\Inherited\InvoiceId;
use Tests\Mocks\ValueObjects\Inherited\LegacyId;
use Tests\Mocks\ValueObjects\Inherited\LocallyOverriddenId;
use Tests\Mocks\ValueObjects\Inherited\ParentWinsId;
use Tests\Mocks\ValueObjects\Inherited\PartiallyOverriddenId;
use Tests\Mocks\ValueObjects\Inherited\PlainId;
use Tests\Mocks\ValueObjects\Inherited\ReceiptId;
use Tests\Mocks\ValueObjects\Inherited\SharedExplicitBrandId;

test('resolves #[Named] on a class to the base name, for both directions', function () {
    $node = new TypeParser()->parse(Customer::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->inputName)->toBe('Customer')
        ->and($node->name?->outputName)->toBe('Customer')
        ->and($node->brand)->toBeNull()
        ->and($node->node)->toBeInstanceOf(CustomCastingNode::class);

    compareToOptimizedAst($node);
    validateAst($node);
});

test('an explicit name wins over the base name', function () {
    $node = new TypeParser()->parse(RenamedThing::class);

    expect($node->name?->inputName)->toBe('CustomThing')
        ->and($node->name?->outputName)->toBe('CustomThing');
});

test('a class without codegen attributes carries no metadata wrapper', function () {
    $node = new TypeParser()->parse(CreateAccountInput::class);

    expect($node)->toBeInstanceOf(CustomCastingNode::class);
});

test('#[Named] on an enum names both directions; its shape is identical either way', function () {
    $node = new TypeParser()->parse(OrderStatus::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->inputName)->toBe('OrderStatus')
        ->and($node->name?->outputName)->toBe('OrderStatus')
        ->and($node->node)->toBeInstanceOf(EnumNode::class);

    compareToOptimizedAst($node);
});

test('a value object can combine #[Brand] and #[Named]', function () {
    $node = new TypeParser()->parse(NamedValueObject::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->inputName)->toBe('AccountId')
        ->and($node->name?->outputName)->toBe('AccountId')
        ->and($node->brand)->toBe('accountId')
        ->and($node->node)->toBeInstanceOf(ValueObjectNode::class);
});

test('metadata is transparent in the string form and eliminated from the optimized AST', function () {
    $node = new TypeParser()->parse(Order::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and((string) $node)->toBe((string) $node->node)
        ->and($node->exportPhpCode())->not->toContain('MetadataNode');

    $optimizedCode = new ASTOptimizer()->generateOptimizedCode(['node' => $node]);

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    expect($optimizedCode)->not->toContain('MetadataNode')
        ->and($registry->get('node'))->not->toBeInstanceOf(MetadataNode::class)
        ->and((string) $registry->get('node'))->toBe((string) $node);
});

test('rejects a name that is not a valid TypeScript identifier', function () {
    expect(fn () => new TypeParser()->parse(InvalidlyNamed::class))
        ->toThrow(InvalidStringLiteralException::class, 'not a valid TypeScript identifier');
});

test('rejects a brand tag that is not a valid TypeScript identifier', function () {
    expect(fn () => new TypeParser()->parse(InvalidlyBranded::class))
        ->toThrow(InvalidStringLiteralException::class, 'not a valid TypeScript identifier');
});

/**
 * Inherited metadata: a value object may declare #[Brand] / #[Named] once on the interface or
 * parent it shares with its siblings. The lookup reaches exactly one level up and derives the
 * brand and alias from the concrete class, so siblings stay distinct types.
 */
test('a value object inherits both attributes from its interface and derives them per class', function (
    string $type,
    string $expectedBrand,
    string $expectedName,
) {
    $node = new TypeParser()->parse($type);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe($expectedBrand)
        ->and($node->name?->outputName)->toBe($expectedName)
        ->and($node->name?->inputName)->toBe($expectedName)
        ->and($node->node)->toBeInstanceOf(ValueObjectNode::class)
        ->and($node->node->className)->toBe($type);

    compareToOptimizedAst($node);
    validateAst($node);
})->with([
    'from an interface' => [AccountId::class, 'accountId', 'AccountId'],
    'sibling of the same interface' => [BrandId::class, 'brandId', 'BrandId'],
    'from an abstract parent class' => [LegacyId::class, 'legacyId', 'LegacyId'],
    'from a concrete parent class' => [ChildId::class, 'childId', 'ChildId'],
]);

test('a concrete parent keeps a brand of its own, distinct from its children', function () {
    $parent = new TypeParser()->parse(BaseId::class);
    $child = new TypeParser()->parse(ChildId::class);

    expect($parent)->toBeInstanceOf(MetadataNode::class)
        ->and($parent->brand)->toBe('baseId')
        ->and($parent->name?->outputName)->toBe('BaseId')
        ->and($child->brand)->toBe('childId')
        ->and($child->name?->outputName)->toBe('ChildId');

    compareToOptimizedAst($parent);
    validateAst($parent);
});

test('a locally declared attribute wins over the inherited one', function () {
    $node = new TypeParser()->parse(LocallyOverriddenId::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe('explicitBrand')
        ->and($node->name?->outputName)->toBe('ExplicitName');

    compareToOptimizedAst($node);
    validateAst($node);
});

test('a local #[Brand] combines with an inherited #[Named]', function () {
    $node = new TypeParser()->parse(PartiallyOverriddenId::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe('partialBrand')
        ->and($node->name?->outputName)->toBe('PartiallyOverriddenId');

    compareToOptimizedAst($node);
    validateAst($node);
});

test('the parent class is consulted before the interfaces', function () {
    // Both carry #[Named]; only the suffix their closure adds tells them apart.
    $node = new TypeParser()->parse(ParentWinsId::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->outputName)->toBe('ParentWinsIdFromParent');
});

test('the lookup stops after one level', function (string $type) {
    $node = new TypeParser()->parse($type);

    expect($node)->toBeInstanceOf(ValueObjectNode::class);

    compareToOptimizedAst($node);
})->with([
    // DeepIntId extends IntId, so IntId's attributes are two levels from the implementor.
    'two levels through interfaces' => DeepId::class,
    // GrandChildId extends ChildId extends BaseId.
    'two levels through classes' => GrandChildId::class,
]);

test('a value object implementing an attribute free interface stays a bare node', function () {
    $node = new TypeParser()->parse(PlainId::class);

    expect($node)->toBeInstanceOf(ValueObjectNode::class);

    compareToOptimizedAst($node);
});

test('rejects a fixed name on an inherited declaration', function () {
    // Every implementor would share the brand "sharedId" and collapse into one TypeScript type.
    expect(fn () => new TypeParser()->parse(SharedExplicitBrandId::class))
        ->toThrow(ParserException::class, 'cannot carry a fixed name');
});

test('inheritance is scoped to value objects: a plain class implementing a #[Named] interface is not named', function () {
    $node = new TypeParser()->parse(ArticleResource::class);

    expect($node)->not->toBeInstanceOf(MetadataNode::class);
});

test('two interfaces declaring the same attribute are ambiguous and rejected', function () {
    expect(fn () => new TypeParser()->parse(AmbiguousId::class))
        ->toThrow(ParserException::class, 'inherits #[Brand] from more than one interface');
});

test('declaring the attribute locally settles an otherwise ambiguous pair of interfaces', function () {
    $node = new TypeParser()->parse(DisambiguatedId::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe('disambiguatedId');

    compareToOptimizedAst($node);
    validateAst($node);
});

/**
 * A naming closure is how an inherited declaration opts out of the default derivation without
 * handing every child the same tag. PHP rejects a closure literal in an attribute argument, so the
 * form is first-class callable syntax: #[Named(name: Naming::suffixedAlias(...))].
 */
test('a naming closure computes the brand and alias from the concrete class', function (
    string $type,
    string $expectedBrand,
    string $expectedName,
) {
    $node = new TypeParser()->parse($type);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->brand)->toBe($expectedBrand)
        ->and($node->name?->outputName)->toBe($expectedName);

    compareToOptimizedAst($node);
    validateAst($node);
})->with([
    // Inherited from ComputedId: each implementor runs the rule against its own name.
    'inherited closure' => [InvoiceId::class, 'appInvoiceId', 'InvoiceIdAlias'],
    'inherited closure, sibling' => [ReceiptId::class, 'appReceiptId', 'ReceiptIdAlias'],
    // The same closures declared directly on a class.
    'local closure' => [ComputedLocally::class, 'appComputedLocally', 'ComputedLocallyAlias'],
]);

test('a naming closure still has to produce a valid TypeScript identifier', function () {
    expect(fn () => new TypeParser()->parse(BadClosureId::class))
        ->toThrow(InvalidStringLiteralException::class, 'not a valid TypeScript identifier');
});

/**
 * The naming closure is also handed the direction, which is the only way to get two aliases out of
 * one declaration — and the only way to name a class whose two shapes differ.
 */
test('a naming closure receives the direction and may return a name per direction', function () {
    $node = new TypeParser()->parse(PerDirectionNamed::class);

    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->inputName)->toBe('PerDirectionNamedInput')
        ->and($node->name?->outputName)->toBe('PerDirectionNamed')
        ->and($node->name?->isSameForBothDirections())->toBeFalse();

    validateAst($node);
});

test('one alias over a class whose input and output shapes differ is rejected', function () {
    // Parsing stays cheap and permissive: only validation, which the code generator runs, refuses it.
    $node = new TypeParser()->parse(AsymmetricNamed::class);
    expect($node)->toBeInstanceOf(MetadataNode::class)
        ->and($node->name?->isSameForBothDirections())->toBeTrue();

    expect(fn () => AstValidator::validate($node))
        ->toThrow(ParserException::class, 'resolves to one alias "AsymmetricNamed" for both directions');
});

test('a named class whose properties are all bidirectional validates cleanly', function () {
    validateAst(new TypeParser()->parse(Customer::class));
});
