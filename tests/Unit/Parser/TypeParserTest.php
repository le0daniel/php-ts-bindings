<?php

namespace Tests\Unit\Parser;

use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Parser\Data\GlobalTypeAliases;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ObjectCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\LiteralType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\LiteralNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\ListNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\RecordNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\StructNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\TupleNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\UnionNode;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Parser\TypeStringTokenizer;
use Le0daniel\PhpTsBindings\Validators\Email;
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
    $parser = new TypeParser(new TypeStringTokenizer());

    expect($node = $parser->parse("string | int"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);
});

test('test literal union', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    /** @var UnionNode $node */
    expect($node = $parser->parse("7|'18'|true"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);

    /**
     * @var int $index
     * @var LiteralNode $type
     */
    foreach ($node->types as $index => $type) {
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
    $parser = new TypeParser(new TypeStringTokenizer());

    $node = $parser->parse(FullAccount::class);

    expect($node)->toBeInstanceOf(ObjectCastingNode::class)
        ->and($node->node)->toBeInstanceOf(StructNode::class)
        ->and($node->node->phpType)->toEqual(StructPhpType::ARRAY)
        ->and($node->strategy)->toEqual(ObjectCastStrategy::NEVER);

    $node = $parser->parse('?'.FullAccount::class);
    expect($node)->toBeInstanceOf(UnionNode::class)
        ->and($node->types[0])->toBeInstanceOf(BuiltInNode::class)
        ->and($node->types[0]->type)->toEqual(BuiltInType::NULL)
        ->and($node->types[1])->toBeInstanceOf(ObjectCastingNode::class)
        ->and($node->types[1]->node)->toBeInstanceOf(StructNode::class)
        ->and($node->types[1]->node->phpType)->toEqual(StructPhpType::ARRAY)
        ->and($node->types[1]->strategy)->toEqual(ObjectCastStrategy::NEVER);
});

test('test scalar', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    /** @var UnionNode $node */
    $node = $parser->parse("scalar");
    expect($node)->toBeInstanceOf(UnionNode::class);

    /**
     * @var int $index
     * @var BuiltInNode $type
     */
    foreach ($node->types as $index => $type) {
        match ($index) {
            0 => expect($type->type)->toEqual(BuiltInType::INT),
            1 => expect($type->type)->toEqual(BuiltInType::FLOAT),
            2 => expect($type->type)->toEqual(BuiltInType::BOOL),
            3 => expect($type->type)->toEqual(BuiltInType::STRING),
        };
    }

    compareToOptimizedAst($node);
});

test('test questionmark nullability support', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse("?float");

    expect($node)->toBeInstanceOf(UnionNode::class);

    expect($node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[0]->type)->toBe(BuiltInType::NULL);

    expect($node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[1]->type)->toBe(BuiltInType::FLOAT);

    compareToOptimizedAst($node);
});

test('test failure on question mark union', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    expect(fn() => $parser->parse("?float|null"))
        ->toThrow("Cannot mix union with intersection or nullable types. Use brackets to do so. Example: (A&B)|C or null|A|B");
});

test('test group support of question mark nullability and flattened result', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse("(?float)|string");

    expect($node)->toBeInstanceOf(UnionNode::class);

    expect($node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[0]->type)->toBe(BuiltInType::NULL);
    expect($node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[1]->type)->toBe(BuiltInType::FLOAT);
    expect($node->types[2])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[2]->type)->toBe(BuiltInType::STRING);

    compareToOptimizedAst($node);
});

test('float', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var BuiltInNode $node */
    $node = $parser->parse("float");

    expect($node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->type)->toEqual(BuiltInType::FLOAT);

    compareToOptimizedAst($node);
});

test('int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var BuiltInNode $node */
    $node = $parser->parse("int");

    expect($node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Generic Int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    $node = $parser->parse("int<0, 100>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBe(0)
        ->and($node->constraints[0]->max)->toBe(100)
        ->and($node->constraints[0]->including)->toBe(true)
        ->and($node->node)->toBeInstanceOf(BuiltInNode::class)
        ->and($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Generic Int Min', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    $node = $parser->parse("int<min, 100>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBe(PHP_INT_MIN)
        ->and($node->constraints[0]->max)->toBe(100)
        ->and($node->constraints[0]->including)->toBe(true)
        ->and($node->node)->toBeInstanceOf(BuiltInNode::class)
        ->and($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Generic Int Max', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    $node = $parser->parse("int<-1, max>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBe(-1)
        ->and($node->constraints[0]->max)->toBe(PHP_INT_MAX)
        ->and($node->constraints[0]->including)->toBe(true)
        ->and($node->node)->toBeInstanceOf(BuiltInNode::class)
        ->and($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Generic Int Negative Values', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    $node = $parser->parse("int<-100, -3>");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0]->min)->toBe(-100)
        ->and($node->constraints[0]->max)->toBe(-3)
        ->and($node->constraints[0]->including)->toBe(true)
        ->and($node->node)->toBeInstanceOf(BuiltInNode::class)
        ->and($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('numeric', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse("numeric");

    /**
     * @var int $index
     * @var BuiltInNode $type
     */
    foreach ($node->types as $index => $type) {
        match ($index) {
            0 => expect($type->type)->toEqual(BuiltInType::INT),
            1 => expect($type->type)->toEqual(BuiltInType::FLOAT),
        };
    }

    compareToOptimizedAst($node);
});

test('Global aliases', function () {
    $parser = new TypeParser(
        new TypeStringTokenizer(),
        TypeParser::defaultConsumers(new GlobalTypeAliases([
            'Email' => fn() => new ConstraintNode(
                new BuiltInNode(BuiltInType::STRING),
                [new Email()],
            ),
        ]))
    );
    /** @var ConstraintNode $node */
    $node = $parser->parse("Email");

    expect($node)->toBeInstanceOf(ConstraintNode::class)
        ->and($node->constraints[0])->toBeInstanceOf(Email::class)
        ->and(count($node->constraints))->toBe(1);

    compareToOptimizedAst($node);
});

test('positive-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("positive-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Local type resolution', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("AddressInput", ParsingContext::fromClassString(Address::class));
    compareToOptimizedAst($node);

    expect($node)->toBeInstanceOf(StructNode::class);
    expect((string) $node)->toBe('array{city: string, street: string, zip: string}');
});

test('Local imported resolution', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("AddressInputData", ParsingContext::fromClassString(MyUserClass::class));
    compareToOptimizedAst($node);

    expect($node)->toBeInstanceOf(StructNode::class);
    expect((string) $node)->toBe('array{city: string, street: string, zip: string}');
});

test('non-negative-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-negative-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('non-positive-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("non-positive-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('negative-int', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ConstraintNode $node */
    $node = $parser->parse("negative-int");

    expect($node)->toBeInstanceOf(ConstraintNode::class);
    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('object struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var StructNode $node */
    $node = $parser->parse("object{a: string, b: int}");
    expect($node)->toBeInstanceOf(StructNode::class);

    expect($node->phpType)->toEqual(StructPhpType::OBJECT);
    expect($node->getProperty('a')->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('a')->node->type)->toEqual(BuiltInType::STRING);

    expect($node->getProperty('b')->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('b')->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('array struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var StructNode $node */
    $node = $parser->parse("array{a: string, b: int}");
    expect($node)->toBeInstanceOf(StructNode::class);

    expect($node->phpType)->toEqual(StructPhpType::ARRAY);
    expect($node->getProperty('a')->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('a')->node->type)->toEqual(BuiltInType::STRING);

    expect($node->getProperty('b')->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->getProperty('b')->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('simplified tuple struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var TupleNode $node */
    $node = $parser->parse("array{string, int}");
    expect($node)->toBeInstanceOf(TupleNode::class);

    expect($node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[0]->type)->toEqual(BuiltInType::STRING);

    expect($node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[1]->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('classic tuple struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var TupleNode $node */
    $node = $parser->parse("array{0:string, 1: int}");
    expect($node)->toBeInstanceOf(TupleNode::class);

    expect($node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[0]->type)->toEqual(BuiltInType::STRING);

    expect($node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->types[1]->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('List struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ListNode $node */
    $node = $parser->parse("array<string>");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::STRING);

    compareToOptimizedAst($node);
});

test('List by modifier', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ListNode $node */
    $node = $parser->parse("string[]");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::STRING);

    compareToOptimizedAst($node);
});

test('Grouped Modifier', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var ListNode $node */
    $node = $parser->parse("(string|int)[]");
    expect($node)->toBeInstanceOf(ListNode::class);

    expect($node->node)->toBeInstanceOf(UnionNode::class);

    expect($node->node->types[0])->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->types[0]->type)->toBe(BuiltInType::STRING);

    expect($node->node->types[1])->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->types[1]->type)->toBe(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Record struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var RecordNode $node */
    $node = $parser->parse("array<string, int>");
    expect($node)->toBeInstanceOf(RecordNode::class);

    expect($node->node)->toBeInstanceOf(BuiltInNode::class);
    expect($node->node->type)->toEqual(BuiltInType::INT);

    compareToOptimizedAst($node);
});

test('Test simple literals', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse("1|-2|true|false|'string'");
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->types as $index => $type) {
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
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse(\DateTime::class);
    expect($node)->toBeInstanceOf(DateTimeNode::class);
    compareToOptimizedAst($node);
});

test('Test date time with a namespace', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse(\DateTime::class, new ParsingContext('SomeName\\Space'));
    expect($node)->toBeInstanceOf(DateTimeNode::class);
    compareToOptimizedAst($node);
});

test('Test EnumCase and class const literal', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var UnionNode $node */
    $node = $parser->parse(
        "ResultEnumBase::SUCCESS|ResultEnumBase::FAILURE|ResultEnum::OTHER",
        new ParsingContext('SomeName\\Space', [
            'ResultEnumBase' => ResultEnum::class,
            'ResultEnum' => ResultEnum::class,
        ]),
    );
    expect($node)->toBeInstanceOf(UnionNode::class);

    foreach ($node->types as $index => $type) {
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
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{id:string}&array{reason:string}');
    expect($node)->toBeInstanceOf(IntersectionNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{id:string,}');
    expect($node)->toBeInstanceOf(StructNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma on object struct', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var IntersectionNode $node */
    $node = $parser->parse('object{id:string,}');
    expect($node)->toBeInstanceOf(StructNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma tuple', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{string, string,}');
    expect($node)->toBeInstanceOf(TupleNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Tailing comma tuple with integer keys', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var IntersectionNode $node */
    $node = $parser->parse('array{0:string, 1:string,}');
    expect($node)->toBeInstanceOf(TupleNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Complex intersection', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    /** @var IntersectionNode $node */
    $node = $parser->parse('(array{id:string}|array{token:string})&array{reason:string}');
    expect($node)->toBeInstanceOf(IntersectionNode::class);
    compareToOptimizedAst($node);
    validateAst($node);
});

test('Generics parsing', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(Paginated::class . '<array{id:string}>');
    expect($node)->toBeInstanceOf(ObjectCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    $typescriptGenerator = new TypescriptDefinitionGenerator();
    $definition = $typescriptGenerator->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($definition)->toBe('{items:Array<{id:string;}>;total:number;}');
});

test('Generics parsing with readonly output properties', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(ReadonlyOutputFields::class);
    expect($node)->toBeInstanceOf(ObjectCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    $typescriptGenerator = new TypescriptDefinitionGenerator();
    $definition = $typescriptGenerator->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($definition)->toBe('{name:string;email:string;}');
});

test('Do not cast in default mode', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(UncastableClass::class);
    expect($node)->toBeInstanceOf(ObjectCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    $typescriptGenerator = new TypescriptDefinitionGenerator();
    $inputDef = $typescriptGenerator->toDefinition($node, DefinitionTarget::INPUT);
    $outputDef = $typescriptGenerator->toDefinition($node, DefinitionTarget::OUTPUT);

    expect($inputDef)->toBe('never');
    expect($outputDef)->toBe('{email:string;name:string;}');
});

test('fails on missing or too many generics', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    expect(fn() => $parser->parse(Paginated::class . '<array{id:string}, array{id:string}>'))
        ->toThrow('Number of generics does not match. Expected 1 <I>, got 2.')
        ->and(fn() => $parser->parse(Paginated::class))
        ->toThrow('Number of generics does not match. Expected 1 <I>, got 0.');
});

test('Test Pick Node simple case', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
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
    $parser = new TypeParser(new TypeStringTokenizer());
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
    $parser = new TypeParser(new TypeStringTokenizer());

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
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse($type);
    compareToOptimizedAst($node);

    $outputDef = typescriptDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe($expectedDefinition);
})->with([
    'Simple Pick' => ['{name:string;}', 'Pick<array{id: string, name: string}, "name">'],
    'Simple Omit' => ['{id:string;}', 'Omit<array{id: string, name: string}, "name">'],
    'Pick multiple' => ['{age:number;name:string;}', 'Pick<array{id: string, name: string, age: int}, "name"|"age">'],
    'Omit multiple' => ['{email:string;id:string;}', 'Omit<array{id: string, name: string, email: string, age: int}, "name"|"age">'],
    'Pick from object' => ['{name:string;}', 'Pick<object{id: string, name: string}, "name">'],
    'Omit from object' => ['{id:string;}', 'Omit<object{id: string, name: string}, "name">'],
    'Pick from class' => ['{username:string;}', 'Pick<' . UserMock::class . ', "username">'],
    'Omit from class' => ['{email:string;username:string;}', 'Omit<' . UserMock::class . ', "age">'],
    'Simple Pick with optional' => ['{name?:string|null;}', 'Pick<array{id?: string, name?: string|null}, "name">'],
    'Simple Omit with optional' => ['{id?:string;}', 'Omit<array{id?: string, name: string}, "name">'],
]);

test("parse interface properties", function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(SomeFileInterface::class);
    compareToOptimizedAst($node);

    $outputDef = typescriptDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe('{id:number;url:string;}');

    $inputDef = typescriptDefinition($node, DefinitionTarget::INPUT);
    expect($inputDef)->toBe('never');
});

test("parse abstract class properties", function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(SomeAbstractClass::class);
    compareToOptimizedAst($node);

    $outputDef = typescriptDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe('{email:string;id:number;}');

    $inputDef = typescriptDefinition($node, DefinitionTarget::INPUT);
    expect($inputDef)->toBe('never');
});

test("parse BrandedInt correctly", function () {
    // Branded types are optimized away. They have no runtime Impact
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse("BrandedInt<'wow'>");
    compareToOptimizedAst($node);

    $tsGenerator = new TypescriptDefinitionGenerator(true);
    $outputDef = $tsGenerator->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe('Branded<number,"wow">');

    $inputDef = $tsGenerator->toDefinition($node, DefinitionTarget::INPUT);
    expect($inputDef)->toBe('Branded<number,"wow">');

    $tsGeneratorWithoutBrand = new TypescriptDefinitionGenerator(false);
    $outputDef = $tsGeneratorWithoutBrand->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe('number');

    $inputDef = $tsGeneratorWithoutBrand->toDefinition($node, DefinitionTarget::INPUT);
    expect($inputDef)->toBe('number');
});

test("parse BrandedString correctly", function () {
    // Branded types are optimized away. They have no runtime Impact
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse("BrandedString<'wow'>");
    compareToOptimizedAst($node);

    $tsGenerator = new TypescriptDefinitionGenerator(true);

    $outputDef = $tsGenerator->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe('Branded<string,"wow">');

    $inputDef = $tsGenerator->toDefinition($node, DefinitionTarget::INPUT);
    expect($inputDef)->toBe('Branded<string,"wow">');

    $tsGeneratorWithoutBrand = new TypescriptDefinitionGenerator(false);
    $outputDef = $tsGeneratorWithoutBrand->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($outputDef)->toBe('string');

    $inputDef = $tsGeneratorWithoutBrand->toDefinition($node, DefinitionTarget::INPUT);
    expect($inputDef)->toBe('string');
});