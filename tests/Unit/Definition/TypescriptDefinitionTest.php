<?php

namespace Tests\Unit\Definition;

use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinition;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Tests\Unit\Executor\Mocks\UserSchema;

function toDefinition(string $typeString, ?DefinitionTarget $mode = null): string
{
    $modes = $mode ? [$mode] : [DefinitionTarget::INPUT, DefinitionTarget::OUTPUT];
    $parser = new TypeParser();
    $ast = $parser->parse($typeString);

    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $ast]);

    /** @var \Le0daniel\PhpTsBindings\Executor\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    $definitionWriter = new TypescriptDefinition();

    /** @var string|null $definition */
    $definition = null;
    foreach ($modes as $mode) {
        $realDef = $definitionWriter->toDefinition($ast, $mode);
        $optimizedDef = $definitionWriter->toDefinition($registry->get('node'), $mode);
        expect($realDef)->toEqual($optimizedDef);
        $definition ??= $realDef;
        expect($definition)->toEqual($realDef);
    }

    return $definition;
}

describe('Test to definition', function () {

    test('Simple union type', function () {
        expect(toDefinition('array{name: string}|string'))
            ->toBe("{name:string;}|string");
    });


    test('Array type returns object', function () {
        expect(toDefinition('array{name: string}'))
            ->toBe("{name:string;}");
    });

    test('Object type returns object', function () {
        expect(toDefinition('object{name: string}'))
            ->toBe("{name:string;}");
    });

    test('Custom class type input', function () {
        expect(toDefinition(UserSchema::class, DefinitionTarget::INPUT))
            ->toBe("{age:number;email:string;username:string;}");
    });

    test('Custom class type output', function () {
        expect(toDefinition(UserSchema::class, DefinitionTarget::OUTPUT))
            ->toBe("{age:number;username:string;}");
    });

    test('scalar', function () {
        expect(toDefinition('scalar'))
            ->toBe("number|boolean|string");
    });

    test('intersection type with union', function () {
        expect(toDefinition('(array{id: positive-int}|array{token: string})&array{reason: string}'))
            ->toBe("({id:number;}|{token:string;})&{reason:string;}");
    });

    test('Complex union intersection', function () {
        expect(toDefinition('((array{id: positive-int}|array{token: string})&array{reason: string})|' . UserSchema::class, DefinitionTarget::INPUT))
            ->toBe("(({id:number;}|{token:string;})&{reason:string;})|{age:number;email:string;username:string;}");
    });
});

