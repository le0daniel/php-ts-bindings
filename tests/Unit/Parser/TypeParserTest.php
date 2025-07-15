<?php

namespace Tests\Unit\Parser;

use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Parser\Data\GlobalTypeAliases;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Nodes\ConstraintNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\BuiltInType;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\StructPhpType;
use Le0daniel\PhpTsBindings\Parser\Nodes\IntersectionNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\BuiltInNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\DateTimeNode;
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
use Tests\Unit\Parser\Data\Stubs\MyUserClass;
use Tests\Unit\Parser\Data\Stubs\ReadonlyOutputFields;
use Tests\Unit\Parser\Data\Stubs\UncastableClass;


test('test simple union', function () {
    $parser = new TypeParser(new TypeStringTokenizer());

    expect($node = $parser->parse("string | int"))
        ->toBeInstanceOf(UnionNode::class);

    compareToOptimizedAst($node);
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
    expect($node)->toBeInstanceOf(CustomCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    $typescriptGenerator = new TypescriptDefinitionGenerator();
    $definition = $typescriptGenerator->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($definition)->toBe('{items:Array<{id:string;}>;total:number;}');
});

test('Generics parsing with readonly output properties', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(ReadonlyOutputFields::class);
    expect($node)->toBeInstanceOf(CustomCastingNode::class);
    compareToOptimizedAst($node);
    validateAst($node);

    $typescriptGenerator = new TypescriptDefinitionGenerator();
    $definition = $typescriptGenerator->toDefinition($node, DefinitionTarget::OUTPUT);
    expect($definition)->toBe('{name:string;email:string;}');
});

test('Do not cast in default mode', function () {
    $parser = new TypeParser(new TypeStringTokenizer());
    $node = $parser->parse(UncastableClass::class);
    expect($node)->toBeInstanceOf(CustomCastingNode::class);
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
