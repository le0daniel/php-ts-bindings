<?php declare(strict_types=1);

namespace Tests\Unit\Typescript;

use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;
use Tests\Unit\Executor\Mocks\UserSchema;

/**
 * An AST that went through the optimizer must generate exactly the same TypeScript as the one the
 * parser produced, otherwise a cached schema would emit a different client than a fresh one.
 */
function toDefinition(string $typeString, ?IO $io = null): string
{
    $directions = $io ? [$io] : [IO::INPUT, IO::OUTPUT];
    $parser = new TypeParser();
    $ast = $parser->parse($typeString);

    $optimizer = new ASTOptimizer();
    $optimizedCode = $optimizer->generateOptimizedCode(['node' => $ast]);

    /** @var \Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry $registry */
    $registry = eval("return {$optimizedCode};");

    $generator = new TypescriptGenerator();

    /** @var string|null $definition */
    $definition = null;
    foreach ($directions as $direction) {
        $realDef = $generator->toTypescript($ast, $direction)->type;
        $optimizedDef = $generator->toTypescript($registry->get('node'), $direction)->type;
        expect($realDef)->toEqual($optimizedDef);
        $definition ??= $realDef;
        expect($definition)->toEqual($realDef);
    }

    return $definition;
}

describe('Test to definition', function () {

    test('Simple union type', function () {
        expect(toDefinition('array{name: string}|string'))
            ->toBe("({name:string;}|string)");
    });

    test('Optional Fields', function () {
        expect(toDefinition('array{name?: string}|string'))
            ->toBe("({name?:string;}|string)");
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
        expect(toDefinition(UserSchema::class, IO::INPUT))
            ->toBe("{age:number;email:string;username:string;}");
    });

    test('Custom class type output', function () {
        expect(toDefinition(UserSchema::class, IO::OUTPUT))
            ->toBe("{age:number;username:string;}");
    });

    test('scalar', function () {
        expect(toDefinition('scalar'))
            ->toBe("(number|boolean|string)");
    });

    test('intersection type with union', function () {
        expect(toDefinition('(array{id: positive-int}|array{token: string})&array{reason: string}'))
            ->toBe("(({id:number;}|{token:string;})&{reason:string;})");
    });

    test('Complex union intersection', function () {
        expect(toDefinition('((array{id: positive-int}|array{token: string})&array{reason: string})|' . UserSchema::class, IO::INPUT))
            ->toBe("((({id:number;}|{token:string;})&{reason:string;})|{age:number;email:string;username:string;})");
    });
});
