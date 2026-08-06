<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Helpers\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\PropertyNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Mocks\Named\AsymmetricNamed;
use Tests\Mocks\Named\BrandedPayload;
use Tests\Mocks\Named\Customer;
use Tests\Mocks\Named\NamedValueObject;
use Tests\Mocks\Named\Order;
use Tests\Mocks\Named\OrderStatus;
use Tests\Mocks\Named\PerDirectionNamed;
use Tests\Mocks\Named\PublicResource;
use Tests\Mocks\Named\RenamedThing;
use Tests\Mocks\ValueObjects\Inherited\AccountId;
use Tests\Mocks\ValueObjects\Inherited\BrandId;

test('a named class is referenced by its alias on output and carries its definition in the registry', function () {
    $node = new TypeParser()->parse(Customer::class);
    $result = typescriptFor($node, IO::OUTPUT);

    expect($result->type)->toBe('Customer')
        ->and($result->registry->usedAliases())->toBe(['Customer'])
        ->and($result->registry->toArray())->toBe([
            'Customer' => '{email:(string & Brand<"email">);name:string;}',
        ]);
});

test('a named class carries its alias on input too, under one shared registry', function () {
    $node = new TypeParser()->parse(Customer::class);
    $shared = new AliasRegistry();

    $result = new TypescriptGenerator()->toTypescript($node, IO::INPUT, $shared);

    expect($result->type)->toBe('Customer')
        ->and($result->registry->toArray())->toBe([
            'Customer' => '{email:(string & Brand<"email">);name:string;}',
        ])
        // One name, one declaration: the input pass hands the very same alias to the shared registry.
        ->and($shared->get('Customer'))->toBe('{email:(string & Brand<"email">);name:string;}');
});

test('named types nest recursively: the outer definition references the inner alias', function () {
    $node = new TypeParser()->parse(Order::class);
    $result = typescriptFor($node, IO::OUTPUT);

    expect($result->type)->toBe('Order')
        ->and($result->registry->usedAliases())->toBe(['Customer', 'Order'])
        ->and($result->registry->toArray())->toBe([
            'Customer' => '{email:(string & Brand<"email">);name:string;}',
            'Order' => '{customer:Customer;id:(number & Brand<"customerId">);}',
        ]);
});

test('nested aliases are referenced on input exactly as they are on output', function () {
    $node = new TypeParser()->parse(Order::class);
    $result = typescriptFor($node, IO::INPUT);

    expect($result->type)->toBe('Order')
        ->and($result->registry->toArray())->toBe([
            'Customer' => '{email:(string & Brand<"email">);name:string;}',
            'Order' => '{customer:Customer;id:(number & Brand<"customerId">);}',
        ]);
});

test('a use site inside a struct references the alias and carries its dependencies as used', function () {
    $node = new TypeParser()->parse('array{order: \\'.Order::class.'}');
    $result = typescriptFor($node, IO::OUTPUT);

    expect($result->type)->toBe('{order:Order;}')
        ->and($result->registry->usedAliases())->toBe(['Customer', 'Order']);
});

test('a brand on a whole class intersects the object shape inline', function () {
    $node = new TypeParser()->parse(BrandedPayload::class);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $result = typescriptFor($node, $io);
        expect($result->type)->toBe('({value:string;} & Brand<"payload">)')
            ->and($result->registry->isEmpty())->toBeTrue();
    }
});

test('Brand and Named combined export the branded type once under the alias', function () {
    $node = new TypeParser()->parse(NamedValueObject::class);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $result = typescriptFor($node, $io);
        expect($result->type)->toBe('AccountId')
            ->and($result->registry->usedAliases())->toBe(['AccountId'])
            ->and($result->registry->toArray())->toBe(['AccountId' => '(string & Brand<"accountId">)']);
    }
});

test('an explicit name is used verbatim', function () {
    $result = typescriptFor(new TypeParser()->parse(RenamedThing::class), IO::OUTPUT);

    expect($result->type)->toBe('CustomThing')
        ->and($result->registry->toArray())->toBe(['CustomThing' => '{value:string;}']);
});

test('a named enum is aliased identically in both directions', function () {
    $node = new TypeParser()->parse(OrderStatus::class);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $result = typescriptFor($node, $io);
        expect($result->type)->toBe('OrderStatus')
            ->and($result->registry->toArray())->toBe(['OrderStatus' => '("OPEN"|"SHIPPED")']);
    }
});

/**
 * The generator emits one direction at a time and never compares the two — which is precisely why
 * AstValidator refuses a single alias over two shapes before generation gets this far.
 */
test('a single pass cannot see the other direction, so nothing catches the divergence here', function () {
    $node = new TypeParser()->parse(AsymmetricNamed::class);
    $generator = new TypescriptGenerator();

    $input = $generator->toTypescript($node, IO::INPUT);
    $output = $generator->toTypescript($node, IO::OUTPUT);

    expect($input->registry->toArray())->toBe(['AsymmetricNamed' => '{secret:string;}'])
        ->and($output->registry->toArray())->toBe(['AsymmetricNamed' => '{visible:string;}']);
});

test('the shared registry is the backstop when the two passes do meet', function () {
    $node = new TypeParser()->parse(AsymmetricNamed::class);
    $generator = new TypescriptGenerator();
    $shared = new AliasRegistry();

    expect($generator->toTypescript($node, IO::INPUT, $shared)->type)->toBe('AsymmetricNamed');

    expect(fn () => $generator->toTypescript($node, IO::OUTPUT, $shared))
        ->toThrow(UnsupportedTypeException::class, 'AsymmetricNamed');
});

test('a name per direction declares both shapes under their own aliases', function () {
    $node = new TypeParser()->parse(PerDirectionNamed::class);
    $generator = new TypescriptGenerator();
    $shared = new AliasRegistry();

    expect($generator->toTypescript($node, IO::INPUT, $shared)->type)->toBe('PerDirectionNamedInput')
        ->and($generator->toTypescript($node, IO::OUTPUT, $shared)->type)->toBe('PerDirectionNamed')
        ->and($shared->toArray())->toBe([
            'PerDirectionNamed' => '{visible:string;}',
            'PerDirectionNamedInput' => '{secret:string;}',
        ]);
});

test('a named interface works on output and stays uncastable on input', function () {
    $node = new TypeParser()->parse(PublicResource::class);

    $result = typescriptFor($node, IO::OUTPUT);
    expect($result->type)->toBe('PublicResource')
        ->and($result->registry->toArray())->toBe(['PublicResource' => '{url:string;}']);

    expect(fn () => typescriptFor($node, IO::INPUT))
        ->toThrow(UnsupportedTypeException::class, PublicResource::class);
});

test('Pick over a named class produces a new shape and drops the alias', function () {
    $result = typescriptFor(
        new TypeParser()->parse('Pick<\\'.Customer::class.", 'name'>"),
        IO::OUTPUT,
    );

    expect($result->type)->toBe('{name:string;}')
        ->and($result->registry->isEmpty())->toBeTrue();
});

test('two named nodes claiming one alias with different shapes are rejected', function () {
    $inner = new MetadataNode(new StringNode(), NamedType::same('Cycle'));
    $outer = new MetadataNode(
        new StructNode(StructPhpType::ARRAY, [
            new PropertyNode('self', $inner, false, PropertyType::BOTH),
        ]),
        NamedType::same('Cycle'),
    );

    expect(fn () => new TypescriptGenerator()->toTypescript($outer, IO::OUTPUT))
        ->toThrow(UnsupportedTypeException::class, 'Cycle');
});

test('siblings inheriting one declaration emit distinct aliases in both directions', function () {
    $node = new TypeParser()->parse(
        'array{account: \\'.AccountId::class.', brand: \\'.BrandId::class.'}',
    );

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $result = typescriptFor($node, $io);

        expect($result->type)->toBe('{account:AccountId;brand:BrandId;}')
            ->and($result->registry->toArray())->toBe([
                'AccountId' => '(number & Brand<"accountId">)',
                'BrandId' => '(number & Brand<"brandId">)',
            ]);
    }
});

test('cached ASTs are metadata free and emit the plain structural type', function () {
    $node = new TypeParser()->parse(Order::class);
    $optimizedCode = new ASTOptimizer()->generateOptimizedCode(['node' => $node]);

    /** @var CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    $result = new TypescriptGenerator()->toTypescript($registry->get('node'), IO::OUTPUT);

    expect($result->type)->toBe('{customer:{email:string;name:string;};id:number;}')
        ->and($result->registry->isEmpty())->toBeTrue();
});
