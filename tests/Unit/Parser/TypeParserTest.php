<?php

namespace Tests\Unit\Parser;

use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Data\GlobalTypeAliases;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\IntRange;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\ListLength;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\LowercaseString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\NonEmptyString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\NumericString;
use Le0daniel\PhpTsBindings\Parser\Helpers\Constraints\UppercaseString;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BoolNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\FloatNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\IntNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\NullNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Feature\Mocks\Paginated;
use Tests\Mocks\ResultEnum;
use Tests\Unit\Parser\Data\Stubs\Address;
use Tests\Unit\Parser\Data\Stubs\FullAccount;
use Tests\Unit\Parser\Data\Stubs\MyUserClass;
use Tests\Unit\Parser\Data\Stubs\ReadonlyOutputFields;
use Tests\Unit\Parser\Data\Stubs\SomeAbstractClass;
use Tests\Unit\Parser\Data\Stubs\SomeFileInterface;
use Tests\Unit\Parser\Data\Stubs\UncastableClass;
use Tests\Unit\Parser\Data\UserMock;


test('test simple union', function () {
    $parser = new TypeParser();

    expect($node = $parser->parse("string | int"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);
});

test('test literal union', function () {
    $parser = new TypeParser();

    /** @var UnionNode $node */
    expect($node = $parser->parse("7|'18'|true"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);

    /**
     * @var int $index
     * @var LiteralNode $type
     */
    foreach ($node->nodes as $index => $type) {
        match ($index) {
            0 => expect($type)->toBeInstanceOf(LiteralNode::class)
                ->and($type->value)->toBe(7)
                ->and($type->type)->toBe(LiteralType::INT),
            1 => expect($type)->toBeInstanceOf(LiteralNode::class)
                ->and($type->value)->toBe('18')
                ->and($type->type)->toBe(LiteralType::STRING),
            2 => expect($type)->toBeInstanceOf(LiteralNode::class)
                ->and($type->value)->toBe(true)
                ->and($type->type)->toBe(LiteralType::BOOL),
        };
    }
});

test('Complex inheritance', function () {
    $parser = new TypeParser();

    $node = $parser->parse(FullAccount::class);

    expect($node)->toBeInstanceOf(CustomCastingNode::class)
        ->and($node->node)->toBeInstanceOf(StructNode::class)
        ->and($node->node->phpType)->toEqual(StructPhpType::ARRAY)
        ->and($node->strategy)->toEqual(ObjectCastStrategy::NEVER);

    $node = $parser->parse('?'.FullAccount::class);
    expect($node)->toBeInstanceOf(UnionNode::class)
        ->and($node->nodes[0])->toBeInstanceOf(NullNode::class)
        ->and($node->nodes[1])->toBeInstanceOf(CustomCastingNode::class)
        ->and($node->nodes[1]->node)->toBeInstanceOf(StructNode::class)
        ->and($node->nodes[1]->node->phpType)->toEqual(StructPhpType::ARRAY)
        ->and($node->nodes[1]->strategy)->toEqual(ObjectCastStrategy::NEVER);
});

test('test scalar', function () {
    $parser = new TypeParser();

    /** @var UnionNode $node */
    $node = $parser->parse("scalar");
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->nodes as $index => $type) {
        match ($index) {
            0 => expect($type)->toBeInstanceOf(IntNode::class),
            1 => expect($type)->toBeInstanceOf(FloatNode::class),
            2 => expect($type)->toBeInstanceOf(BoolNode::class),
            3 => expect($type)->toBeInstanceOf(StringNode::class),
        };
    }

    compareToOptimizedAst($node);
});

test('test questionmark nullability support', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse("?float");

    expect($node)->toBeInstanceOf(UnionNode::class);

    expect($node->nodes[0])->toBeInstanceOf(NullNode::class);
    expect($node->nodes[1])->toBeInstanceOf(FloatNode::class);

    compareToOptimizedAst($node);
});

test('test failure on question mark union', function () {
    $parser = new TypeParser();

    expect(fn() => $parser->parse("?float|null"))
        ->toThrow("Cannot mix union with intersection or nullable types. Use brackets to do so. Example: (A&B)|C or null|A|B");
});

test('test group support of question mark nullability and flattened result', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse("(?float)|string");

    expect($node)->toBeInstanceOf(UnionNode::class);

    expect($node->nodes[0])->toBeInstanceOf(NullNode::class);
    expect($node->nodes[1])->toBeInstanceOf(FloatNode::class);
    expect($node->nodes[2])->toBeInstanceOf(StringNode::class);

    compareToOptimizedAst($node);
});

test('float', function () {
    $parser = new TypeParser();
    $node = $parser->parse("float");

    expect($node)->toBeInstanceOf(FloatNode::class);

    compareToOptimizedAst($node);
});

test('int', function () {
    $parser = new TypeParser();
    $node = $parser->parse("int");

    expect($node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('Generic Int', function () {
    $parser = new TypeParser();

    $node = $parser->parse("int<0, 100>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0])->toBeInstanceOf(IntRange::class)
        ->and($node->constraints[0]->min)->toBe(0)
        ->and($node->constraints[0]->max)->toBe(100)
        ->and($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('Generic Int Min', function () {
    $parser = new TypeParser();

    $node = $parser->parse("int<min, 100>");

    // `min` is an absent bound, not PHP_INT_MIN: the type says there is no lower limit.
    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBeNull()
        ->and($node->constraints[0]->max)->toBe(100)
        ->and($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('Generic Int Max', function () {
    $parser = new TypeParser();

    $node = $parser->parse("int<-1, max>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBe(-1)
        ->and($node->constraints[0]->max)->toBeNull()
        ->and($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('Generic Int Negative Values', function () {
    $parser = new TypeParser();

    $node = $parser->parse("int<-100, -3>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBe(-100)
        ->and($node->constraints[0]->max)->toBe(-3)
        ->and($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('numeric', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse("numeric");

    foreach ($node->nodes as $index => $type) {
        match ($index) {
            0 => expect($type)->toBeInstanceOf(IntNode::class),
            1 => expect($type)->toBeInstanceOf(FloatNode::class),
        };
    }

    compareToOptimizedAst($node);
});

test('Global aliases', function () {
    $parser = new TypeParser(
        TypeParser::defaultConsumers(new GlobalTypeAliases([
            'Slug' => fn() => new ConstraintNode(
                new StringNode(),
                [new NonEmptyString()],
            ),
        ]))
    );
    /** @var ConstraintNode $node */
    $node = $parser->parse("Slug");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0])->toBeInstanceOf(NonEmptyString::class)
        ->and(count($node->constraints))->toBe(1);

    compareToOptimizedAst($node);
});

test('positive-int', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("positive-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('Local type resolution', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("AddressInput", ParsingScope::fromClassString(Address::class));
    compareToOptimizedAst($node);

    expect($node)->toBeInstanceOf(StructNode::class);
    expect((string) $node)->toBe('array{city: string, street: string, zip: string}');
});

test('Local imported resolution', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("AddressInputData", ParsingScope::fromClassString(MyUserClass::class));
    compareToOptimizedAst($node);

    expect($node)->toBeInstanceOf(StructNode::class);
    expect((string) $node)->toBe('array{city: string, street: string, zip: string}');
});

test('non-negative-int', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-negative-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('non-positive-int', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-positive-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('negative-int', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("negative-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('string refinements constrain a StringNode', function (string $type, array $expectedConstraints) {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse($type);

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->node)->toBeInstanceOf(StringNode::class)
        ->and(array_map(fn($constraint) => $constraint::class, $node->constraints))
        ->toBe($expectedConstraints);

    compareToOptimizedAst($node);
})->with([
    ['non-empty-string', [NonEmptyString::class]],
    ['numeric-string', [NumericString::class]],
    ['lowercase-string', [LowercaseString::class]],
    ['uppercase-string', [UppercaseString::class]],
    ['non-empty-lowercase-string', [NonEmptyString::class, LowercaseString::class]],
    ['non-empty-uppercase-string', [NonEmptyString::class, UppercaseString::class]],
]);

/**
 * The `non-empty-` prefix used to be normalised away, so `non-empty-list<int>` parsed to a bare
 * ListNode and accepted the empty list it forbids.
 */
test('non-empty-list keeps its minimum', function () {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-empty-list<int>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0])->toBeInstanceOf(ListLength::class)
        ->and($node->constraints[0]->min)->toBe(1)
        ->and($node->node)->toBeInstanceOf(ListNode::class);

    compareToOptimizedAst($node);
});

test('non-empty-array keeps its minimum over both key types', function (string $type, string $expectedNode) {
    $parser = new TypeParser();
    /** @var ConstraintNode $node */
    $node = $parser->parse($type);

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0])->toBeInstanceOf(ListLength::class)
        ->and($node->constraints[0]->min)->toBe(1)
        ->and($node->node)->toBeInstanceOf($expectedNode);

    compareToOptimizedAst($node);
})->with([
    ['non-empty-array<string, int>', RecordNode::class],
    ['non-empty-array<int, int>', ListNode::class],
]);

test('the plain list and array types carry no constraint', function (string $type) {
    $node = new TypeParser()->parse($type);

    expect($node)->not->toBeInstanceOf(ConstraintNode::class);

    compareToOptimizedAst($node);
})->with(['list<int>', 'array<string, int>', 'array<int, int>']);

test('a bare array or list is rejected rather than degraded', function (string $type) {
    // PHPStan's bare `array` is array<mixed, mixed> and permits string keys. Modelling it as a
    // list would drop those keys on the way out, so it fails like bare `object` does.
    expect(fn() => new TypeParser()->parse($type))
        ->toThrow(InvalidSyntaxException::class, 'has no single representation');
})->with(['array', 'list', 'non-empty-array', 'non-empty-list']);

test('object struct', function () {
    $parser = new TypeParser();
    /** @var StructNode $node */
    $node = $parser->parse("object{a: string, b: int}");
    expect($node)->toBeInstanceOf(StructNode::class);

    expect($node->phpType)->toEqual(StructPhpType::OBJECT);
    expect($node->getProperty('a')->node)->toBeInstanceOf(StringNode::class);
    expect($node->getProperty('b')->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('array struct', function () {
    $parser = new TypeParser();
    /** @var StructNode $node */
    $node = $parser->parse("array{a: string, b: int}");
    expect($node)->toBeInstanceOf(StructNode::class);

    expect($node->phpType)->toEqual(StructPhpType::ARRAY);
    expect($node->getProperty('a')->node)->toBeInstanceOf(StringNode::class);
    expect($node->getProperty('b')->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('simplified tuple struct', function () {
    $parser = new TypeParser();
    /** @var TupleNode $node */
    $node = $parser->parse("array{string, int}");
    expect($node)->toBeInstanceOf(TupleNode::class);

    expect($node->nodes[0])->toBeInstanceOf(StringNode::class);
    expect($node->nodes[1])->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('classic tuple struct', function () {
    $parser = new TypeParser();
    /** @var TupleNode $node */
    $node = $parser->parse("array{0:string, 1: int}");
    expect($node)->toBeInstanceOf(TupleNode::class);

    expect($node->nodes[0])->toBeInstanceOf(StringNode::class);
    expect($node->nodes[1])->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('List struct', function () {
    $parser = new TypeParser();
    /** @var ListNode $node */
    $node = $parser->parse("array<string>");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->node)->toBeInstanceOf(StringNode::class);

    compareToOptimizedAst($node);
});

test('List by modifier', function () {
    $parser = new TypeParser();
    /** @var ListNode $node */
    $node = $parser->parse("string[]");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->node)->toBeInstanceOf(StringNode::class);

    compareToOptimizedAst($node);
});

test('Grouped Modifier', function () {
    $parser = new TypeParser();
    /** @var ListNode $node */
    $node = $parser->parse("(string|int)[]");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->node)->toBeInstanceOf(UnionNode::class);

    expect($node->node->nodes[0])->toBeInstanceOf(StringNode::class);
    expect($node->node->nodes[1])->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('Record struct', function () {
    $parser = new TypeParser();
    /** @var RecordNode $node */
    $node = $parser->parse("array<string, int>");
    expect($node)->toBeInstanceOf(RecordNode::class);

    expect($node->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
});

test('a constrained array key is rejected, the constraint would be silently unenforceable', function (string $type) {
    expect(fn() => new TypeParser()->parse($type))
        ->toThrow(InvalidSyntaxException::class, "Array key type must be 'string' or 'int'");
})->with([
    'non-empty-string key' => ['array<non-empty-string, int>'],
    'positive-int key' => ['array<positive-int, string>'],
]);

test('a branded array key is still a plain string or int key on the wire', function (string $type, string $expected) {
    $node = new TypeParser()->parse($type);

    expect($node::class)->toBe($expected);

    compareToOptimizedAst($node);
})->with([
    'branded string key' => ["array<BrandedString<'k'>, int>", RecordNode::class],
    'branded int key' => ["array<BrandedInt<'k'>, string>", ListNode::class],
]);

test('Test simple literals', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse("1|-2|true|false|'string'");
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->nodes as $index => $type) {
        match ($index) {
            0 => expect($type->value)->toBe(1),
            1 => expect($type->value)->toBe(-2),
            2 => expect($type->value)->toBe(true),
            3 => expect($type->value)->toBe(false),
            4 => expect($type->value)->toBe('string'),
            default => null,
        };
    }

    compareToOptimizedAst($node);
});

test('Test date time literals', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse(\DateTime::class);
    expect($node)->toBeInstanceOf(DateTimeNode::class);
    compareToOptimizedAst($node);
});

test('Test date time with a namespace', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse(\DateTime::class, new ParsingScope('SomeName\\Space'));
    expect($node)->toBeInstanceOf(DateTimeNode::class);
    compareToOptimizedAst($node);
});

test('Test EnumCase and class const literal', function () {
    $parser = new TypeParser();
    /** @var UnionNode $node */
    $node = $parser->parse(
        "ResultEnumBase::SUCCESS|ResultEnumBase::FAILURE|ResultEnum::OTHER",
        new ParsingScope('SomeName\\Space', [
            'ResultEnumBase' => ResultEnum::class,
            'ResultEnum' => ResultEnum::class,
        ]),
    );
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->nodes as $index => $type) {
        match ($index) {
            0 => expect($type->value)->toBe(ResultEnum::SUCCESS),
            1 => expect($type->value)->toBe(ResultEnum::FAILURE),
            2 => expect($type->value)->toBe('other'),
            default => throw new \RuntimeException("Should not be reached"),
        };
    }

    compareToOptimizedAst($node);
});

test('Simple intersection', function () {
    $parser = new TypeParser();
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{id:string}&array{reason:string}');
    expect($node)->toBeInstanceOf(IntersectionNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma', function () {
    $parser = new TypeParser();
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{id:string,}');
    expect($node)->toBeInstanceOf(StructNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma on object struct', function () {
    $parser = new TypeParser();
    /** @var IntersectionNode $node */
    $node = $parser->parse('object{id:string,}');
    expect($node)->toBeInstanceOf(StructNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma tuple', function () {
    $parser = new TypeParser();
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{string, string,}');
    expect($node)->toBeInstanceOf(TupleNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma tuple with integer keys', function () {
    $parser = new TypeParser();
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{0:string, 1:string,}');
    expect($node)->toBeInstanceOf(TupleNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Complex intersection', function () {
    $parser = new TypeParser();
    /** @var IntersectionNode $node */
    $node = $parser->parse('(array{id:string}|array{token:string})&array{reason:string}');
    expect($node)->toBeInstanceOf(IntersectionNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Generics parsing', function () {
    $parser = new TypeParser();
    $node = $parser->parse(Paginated::class . '<array{id:string}>');
    expect($node)->toBeInstanceOf(CustomCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    expect(typescriptFor($node, IO::OUTPUT)->type)->toBe('{items:Array<{id:string;}>;total:number;}');
});

test('Generics parsing with readonly output properties', function () {
    $parser = new TypeParser();
    $node = $parser->parse(ReadonlyOutputFields::class);
    expect($node)->toBeInstanceOf(CustomCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    // Struct properties are canonically ordered at construction, so emission is alphabetical
    // regardless of declaration order.
    expect(new TypescriptGenerator()->toTypescript($node, IO::OUTPUT)->type)
        ->toBe('{email:string;name:string;}');
});

test('Do not cast in default mode', function () {
    $parser = new TypeParser();
    $node = $parser->parse(UncastableClass::class);
    expect($node)->toBeInstanceOf(CustomCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    expect(typescriptFor($node, IO::OUTPUT)->type)->toBe('{email:string;name:string;}')
        ->and(fn() => typescriptFor($node, IO::INPUT))
        ->toThrow(UnsupportedTypeException::class, UncastableClass::class);
});

test('fails on missing or too many generics', function () {
    $parser = new TypeParser();

    expect(fn() => $parser->parse(Paginated::class . '<array{id:string}, array{id:string}>'))
        ->toThrow('Number of generics does not match. Expected 1 <I>, got 2.')
        ->and(fn() => $parser->parse(Paginated::class))
        ->toThrow('Number of generics does not match. Expected 1 <I>, got 0.');
});

test('Test Pick Node simple case', function () {
    $parser = new TypeParser();
    /** @var StructNode $node */
    $node = $parser->parse("Pick<array{id: string, name: string}, 'id'>");
    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->properties)->toHaveCount(1)
        ->and($node->hasProperty('id'))->toBeTrue()
        ->and($node->getProperty('id')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->phpType)->toEqual(StructPhpType::ARRAY);

    /** @var StructNode $node */
    $node = $parser->parse("Pick<object{id: string, name: string}, 'id'>");
    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->properties)->toHaveCount(1)
        ->and($node->hasProperty('id'))->toBeTrue()
        ->and($node->getProperty('id')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->phpType)->toEqual(StructPhpType::OBJECT);
    ;
    compareToOptimizedAst($node);
});

test('Test Omit Node simple case', function () {
    $parser = new TypeParser();
    /** @var StructNode $node */
    $node = $parser->parse("Omit<array{id: string, name: string}, 'id'>");
    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->properties)->toHaveCount(1)
        ->and($node->hasProperty('name'))->toBeTrue()
        ->and($node->getProperty('name')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->phpType)->toEqual(StructPhpType::ARRAY);

    /** @var StructNode $node */
    $node = $parser->parse("Omit<object{id: string, name: string}, 'id'>");
    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->properties)->toHaveCount(1)
        ->and($node->hasProperty('name'))->toBeTrue()
        ->and($node->getProperty('name')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->phpType)->toEqual(StructPhpType::OBJECT);

    compareToOptimizedAst($node);
});

test('Test Pick and Omit Node with custom class', function () {
    $parser = new TypeParser();

    /** @var StructNode $node */
    $node = $parser->parse("Pick<" . UserMock::class . ", 'username'>");
    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->properties)->toHaveCount(1)
        ->and($node->hasProperty('username'))->toBeTrue()
        ->and($node->getProperty('username')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->phpType)->toEqual(StructPhpType::OBJECT);

    compareToOptimizedAst($node);

    /** @var StructNode $node */
    $node = $parser->parse("Omit<" . UserMock::class . ", 'username'>");
    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->properties)->toHaveCount(2)
        ->and($node->getProperty('age')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->getProperty('email')->propertyType)->toEqual(PropertyType::BOTH)
        ->and($node->phpType)->toEqual(StructPhpType::OBJECT);

    compareToOptimizedAst($node);
});

test('Pick and Omit Typescript definitions', function (string $expectedDefinition, string $type) {
    $parser = new TypeParser();
    $node = $parser->parse($type);
    compareToOptimizedAst($node);

    expect(typescriptFor($node, IO::OUTPUT)->type)->toBe($expectedDefinition);
})->with([
    'Simple Pick' => ['{name:string;}', 'Pick<array{id: string, name: string}, "name">'],
    'Simple Omit' => ['{id:string;}', 'Omit<array{id: string, name: string}, "name">'],
    'Pick multiple' => ['{age:number;name:string;}', 'Pick<array{id: string, name: string, age: int}, "name"|"age">'],
    'Omit multiple' => ['{email:string;id:string;}', 'Omit<array{id: string, name: string, email: string, age: int}, "name"|"age">'],
    'Pick from object' => ['{name:string;}', 'Pick<object{id: string, name: string}, "name">'],
    'Omit from object' => ['{id:string;}', 'Omit<object{id: string, name: string}, "name">'],
    'Pick from class' => ['{username:string;}', 'Pick<' . UserMock::class . ', "username">'],
    'Omit from class' => ['{email:string;username:string;}', 'Omit<' . UserMock::class . ', "age">'],
    'Simple Pick with optional' => ['{name?:(string|null);}', 'Pick<array{id?: string, name?: string|null}, "name">'],
    'Simple Omit with optional' => ['{id?:string;}', 'Omit<array{id?: string, name: string}, "name">'],
]);

test("parse interface properties", function () {
    $parser = new TypeParser();
    $node = $parser->parse(SomeFileInterface::class);
    compareToOptimizedAst($node);

    expect(typescriptFor($node, IO::OUTPUT)->type)->toBe('{id:number;url:string;}')
        ->and(fn() => typescriptFor($node, IO::INPUT))
        ->toThrow(UnsupportedTypeException::class, SomeFileInterface::class);
});

test("parse abstract class properties", function () {
    $parser = new TypeParser();
    $node = $parser->parse(SomeAbstractClass::class);
    compareToOptimizedAst($node);

    expect(typescriptFor($node, IO::OUTPUT)->type)->toBe('{email:string;id:number;}')
        ->and(fn() => typescriptFor($node, IO::INPUT))
        ->toThrow(UnsupportedTypeException::class, SomeAbstractClass::class);
});

test("parse BrandedInt correctly", function () {
    $parser = new TypeParser();
    $node = $parser->parse("BrandedInt<'wow'>");
    compareToOptimizedAst($node);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $branded = typescriptFor($node, $io);
        expect($branded->type)->toBe('Wow')
            ->and($branded->registry->toArray())->toBe(['Wow' => '(number & Brand<"wow">)']);
    }
});

test("parse BrandedString correctly", function () {
    $parser = new TypeParser();
    $node = $parser->parse("BrandedString<'wow'>");
    compareToOptimizedAst($node);

    foreach ([IO::INPUT, IO::OUTPUT] as $io) {
        $branded = typescriptFor($node, $io);
        expect($branded->type)->toBe('Wow')
            ->and($branded->registry->toArray())->toBe(['Wow' => '(string & Brand<"wow">)']);
    }
});

test('rejects a branded utility tag that is not a valid TypeScript identifier', function (string $type) {
    expect(fn() => new TypeParser()->parse($type))
        ->toThrow(
            \Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException::class,
            'not a valid TypeScript identifier',
        );
})->with([
    'BrandedString' => ["BrandedString<'not valid'>"],
    'BrandedInt' => ["BrandedInt<'not valid'>"],
]);

test('codegen metadata is transparent in the string form and the exported php code', function () {
    $parser = new TypeParser();
    $string = $parser->parse("BrandedString<'wow'>");
    $int = $parser->parse("BrandedInt<'wow'>");

    expect($string)->toBeInstanceOf(MetadataNode::class)
        ->and($string->brand)->toBe('wow')
        ->and($string->name?->outputName)->toBe('Wow')
        ->and($string->node)->toBeInstanceOf(StringNode::class)
        ->and((string)$string)->toBe('string')
        ->and($string->exportPhpCode())->not->toContain('wow')
        ->and($int)->toBeInstanceOf(MetadataNode::class)
        ->and($int->brand)->toBe('wow')
        ->and($int->node)->toBeInstanceOf(IntNode::class)
        ->and((string)$int)->toBe('int')
        ->and($int->exportPhpCode())->not->toContain('wow');
});

test('a questionmark union accepts null', function () {
    /** @var UnionNode $node */
    $node = new TypeParser()->parse('?bool');

    expect($node)->toBeInstanceOf(UnionNode::class)
        ->and($node->acceptsNull())->toBeTrue()
        ->and($node->nodes[0])->toBeInstanceOf(NullNode::class)
        ->and($node->nodes[1])->toBeInstanceOf(BoolNode::class);

    compareToOptimizedAst($node);
});

test('DateTimeString without a format defaults to ATOM', function () {
    $parser = new TypeParser();
    $node = $parser->parse('DateTimeString');

    expect($node)->toBeInstanceOf(DateTimeNode::class)
        ->and($node->dateTimeClass)->toBe(\DateTimeImmutable::class)
        ->and($node->format)->toBe(\DateTimeInterface::ATOM);

    compareToOptimizedAst($node);
});

test('DateTimeString without a format is indistinguishable from DateTimeImmutable', function () {
    // Both produce the same node, so they share a hash and dedupe into one registry entry.
    $parser = new TypeParser();

    expect((string)$parser->parse('DateTimeString'))
        ->toBe((string)$parser->parse('\DateTimeImmutable'));
});

test('DateTimeString takes the format from its single generic', function (string $type, string $expectedFormat) {
    $parser = new TypeParser();
    $node = $parser->parse($type);

    expect($node)->toBeInstanceOf(DateTimeNode::class)
        ->and($node->dateTimeClass)->toBe(\DateTimeImmutable::class)
        ->and($node->format)->toBe($expectedFormat);

    compareToOptimizedAst($node);
})->with([
    'single quoted' => ["DateTimeString<'Y-m-d'>", 'Y-m-d'],
    'double quoted' => ['DateTimeString<"Y-m-d">', 'Y-m-d'],
    'spaces in the format' => ["DateTimeString<'d.m.Y H:i'>", 'd.m.Y H:i'],
    'padded generic' => ["DateTimeString< 'Y-m-d' >", 'Y-m-d'],

    // Date formats escape literal characters with a backslash. Single quotes only resolve
    // \\ and \', so the escape survives untouched.
    'single quoted escape' => ["DateTimeString<'Y-m-d\\TH:i:sP'>", 'Y-m-d\TH:i:sP'],

    // Double quotes resolve the full PHP escape set, but only the lowercase ones, so an
    // uppercase \T is still safe.
    'double quoted uppercase escape' => ['DateTimeString<"Y-m-d\TH:i:sP">', 'Y-m-d\TH:i:sP'],
]);

test('a double quoted format resolves lowercase escape sequences', function () {
    // Documented gotcha: "\t" is a TAB, not an escaped `t` day-count specifier. Single
    // quotes are the safe choice for date formats.
    $parser = new TypeParser();

    expect($parser->parse('DateTimeString<"H:i\t">')->format)->toBe("H:i\t")
        ->and($parser->parse("DateTimeString<'H:i\\t'>")->format)->toBe('H:i\t');
});

test('DateTimeString is emitted as a string in Typescript', function () {
    $parser = new TypeParser();
    $node = $parser->parse("DateTimeString<'Y-m-d'>");

    expect(typescriptFor($node, IO::INPUT)->type)->toBe('string')
        ->and(typescriptFor($node, IO::OUTPUT)->type)->toBe('string');
});

test('DateTimeString composes with other types', function (string $type, string $expectedDefinition) {
    $parser = new TypeParser();
    $node = $parser->parse($type);

    compareToOptimizedAst($node);
    expect(typescriptFor($node, IO::OUTPUT)->type)->toBe($expectedDefinition);
})->with([
    'nullable' => ["DateTimeString<'Y-m-d'>|null", '(string|null)'],
    'questionmark nullable' => ["?DateTimeString<'Y-m-d'>", '(null|string)'],
    'in a struct' => ["array{createdAt: DateTimeString<'Y-m-d'>}", '{createdAt:string;}'],
    'in a list' => ['list<DateTimeString>', 'Array<string>'],
    'bracket list' => ["DateTimeString<'Y-m-d'>[]", 'Array<string>'],
]);

test('DateTimeString rejects invalid generics', function (string $type) {
    expect(fn() => new TypeParser()->parse($type))->toThrow(InvalidSyntaxException::class);
})->with([
    'empty generics' => ['DateTimeString<>'],
    'two generics' => ["DateTimeString<'Y-m-d','H:i'>"],
    'unterminated generics' => ["DateTimeString<'Y-m-d'"],
    'a type instead of a literal' => ['DateTimeString<int>'],
    'an int literal' => ['DateTimeString<123>'],
    'a union of literals' => ["DateTimeString<'Y-m-d'|'H:i'>"],
]);

/**
 * ---------------------------------------------------------------------------
 * Lexer migration: new functionality
 *
 * These constructs are unreachable through the old TypeStringTokenizer and
 * become parseable once TypeParser runs on Parser\Lexer\Lexer.
 * ---------------------------------------------------------------------------
 */

test('Array shape keys may be double quoted', function () {
    /** @var StructNode $node */
    $node = new TypeParser()->parse('array{"key something else": string, b: int}');

    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->phpType)->toBe(StructPhpType::ARRAY)
        ->and($node->hasProperty('key something else'))->toBeTrue()
        ->and($node->getProperty('key something else')?->node)->toBeInstanceOf(StringNode::class)
        ->and($node->hasProperty('b'))->toBeTrue()
        ->and($node->getProperty('b')?->node)->toBeInstanceOf(IntNode::class);

    compareToOptimizedAst($node);
    validateAst($node);
});

test('Array shape keys may be single quoted and optional', function () {
    /** @var StructNode $node */
    $node = new TypeParser()->parse("object{'k': int, 'two words'?: string}");

    expect($node)->toBeInstanceOf(StructNode::class)
        ->and($node->phpType)->toBe(StructPhpType::OBJECT)
        ->and($node->getProperty('k')?->isOptional)->toBeFalse()
        ->and($node->getProperty('two words')?->isOptional)->toBeTrue();

    compareToOptimizedAst($node);
});

test('Quoted keys with spaces emit valid Typescript', function () {
    $node = new TypeParser()->parse('array{"key something else": string}');

    expect(typescriptFor($node, IO::OUTPUT)->type)
        ->toBe('{"key something else":string;}');
});

test('Quoted key escapes are resolved', function () {
    /** @var StructNode $node */
    $node = new TypeParser()->parse("array{'it\\'s': string}");

    expect($node->hasProperty("it's"))->toBeTrue();
});

test('String literals honour escapes', function () {
    // The old tokenizer threw RuntimeException('Unclosed block type') on both of these.
    /** @var UnionNode $node */
    $node = new TypeParser()->parse("'it\\'s'|\"say \\\"hi\\\"\"");

    expect($node)->toBeInstanceOf(UnionNode::class)
        ->and($node->nodes[0])->toBeInstanceOf(LiteralNode::class)
        ->and($node->nodes[0]->type)->toBe(LiteralType::STRING)
        ->and($node->nodes[0]->value)->toBe("it's")
        ->and($node->nodes[1]->value)->toBe('say "hi"');
});

test('Whitespace between brackets is allowed', function () {
    // The old tokenizer merged [ and ] with a one character lookahead.
    expect(new TypeParser()->parse('string[ ]'))->toBeInstanceOf(ListNode::class)
        ->and(new TypeParser()->parse('string[]'))->toBeInstanceOf(ListNode::class);
});

test('A trailing double colon is a syntax error and raises no PHP warning', function () {
    // The old tokenizer read $typeString[$currentOffset + 2] unguarded, which emitted
    // "PHP Warning: Uninitialized string offset 5".
    $warnings = [];
    set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
        $warnings[] = $message;
        return true;
    });

    try {
        expect(fn() => new TypeParser()->parse('Foo::'))
            ->toThrow(InvalidSyntaxException::class);
    } finally {
        restore_error_handler();
    }

    expect($warnings)->toBe([]);
});

test('Illegal characters raise InvalidSyntaxException, not a lexer exception', function () {
    // Regexes::findFirstVarDeclaration() used to leak the closing */ out of single line
    // docblocks. It no longer does, but the parser stays defensive about it.
    expect(fn() => new TypeParser()->parse('array{id: string} */'))
        ->toThrow(InvalidSyntaxException::class)
        ->and(fn() => new TypeParser()->parse('a#b'))
        ->toThrow(InvalidSyntaxException::class)
        ->and(fn() => new TypeParser()->parse('%'))
        ->toThrow(InvalidSyntaxException::class)
        ->and(fn() => new TypeParser()->parse("array{'unterminated: int}"))
        ->toThrow(InvalidSyntaxException::class);
});

test('A truncated array shape is a syntax error, not a PHP Error', function () {
    // Before the migration this crashed with:
    // "Call to a member function isAnyTypeOf() on null".
    expect(fn() => new TypeParser()->parse('array{'))
        ->toThrow(InvalidSyntaxException::class)
        ->and(fn() => new TypeParser()->parse('array{a'))
        ->toThrow(InvalidSyntaxException::class);
});

/**
 * ---------------------------------------------------------------------------
 * Lexer migration: gap locks
 *
 * The new lexer happily tokenizes all of these. The parser deliberately does
 * not support them, and this test pins that boundary.
 * ---------------------------------------------------------------------------
 */

test('Constructs the lexer accepts but the parser does not support', function () {
    $unsupported = [
        'array{foo: int, ...}',
        'array{...}',
        'array{}',
        'callable(int): void',
        'Closure(int, ...): void',
        '$this',
        'Foo::*',
        'Foo<T = int>',
        '($x is int ? string : bool)',
    ];

    foreach ($unsupported as $type) {
        expect(fn() => new TypeParser()->parse($type))
            ->toThrow(InvalidSyntaxException::class, message: "Should reject: {$type}");
    }
});

/**
 * ---------------------------------------------------------------------------
 * Lexer migration: regression guards
 * ---------------------------------------------------------------------------
 */

test('Literal booleans stay literals and null stays a built in', function () {
    // true/false used to be their own TokenType::BOOL; they are plain IDENTIFIERs now.
    /** @var UnionNode $node */
    $node = new TypeParser()->parse('true|false');

    expect($node->nodes[0])->toBeInstanceOf(LiteralNode::class)
        ->and($node->nodes[0]->type)->toBe(LiteralType::BOOL)
        ->and($node->nodes[0]->value)->toBeTrue()
        ->and($node->nodes[1]->type)->toBe(LiteralType::BOOL)
        ->and($node->nodes[1]->value)->toBeFalse()
        ->and(new TypeParser()->parse('null'))->toBeInstanceOf(NullNode::class);
});

test('Numeric literal forms decode correctly', function () {
    // 1e5 already worked by accident via filter_var; 1_000 and 0x1F used to die
    // with "No parser found." because they fell through to IDENTIFIER.
    /** @var LiteralNode $exponent */
    $exponent = new TypeParser()->parse('1e5');
    /** @var LiteralNode $separated */
    $separated = new TypeParser()->parse('1_000');
    /** @var LiteralNode $hex */
    $hex = new TypeParser()->parse('0x1F');

    expect($exponent->type)->toBe(LiteralType::FLOAT)
        ->and($exponent->value)->toBe(100000.0)
        ->and($separated->type)->toBe(LiteralType::INT)
        ->and($separated->value)->toBe(1000)
        ->and($hex->type)->toBe(LiteralType::INT)
        ->and($hex->value)->toBe(31);
});
