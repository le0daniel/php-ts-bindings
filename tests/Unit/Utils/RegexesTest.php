<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Utils\Regexes;

test('single line declarations are returned as written', function () {
    $docBlock = <<<'DOC'
    /**
     * @param non-empty-string $name
     * @param array{amount: string, birthdate: \DateTime} $age
     * @return array{string, int}
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'name'))
        ->toBe('non-empty-string')
        ->and(Regexes::findParamWithNameDeclaration($docBlock, 'age'))
        ->toBe('array{amount: string, birthdate: \DateTime}')
        ->and(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBe('array{string, int}');
});

test('multiline param declarations are joined into a single type', function () {
    $docBlock = <<<'DOC'
    /**
     * @param array{
     *   key: string
     * } $input
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBe('array{ key: string }');
});

test('multiline return declarations are joined into a single type', function () {
    $docBlock = <<<'DOC'
    /**
     * @return array{
     *   key: string
     * }
     */
    DOC;

    expect(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBe('array{ key: string }');
});

test('multiline var declarations are joined into a single type', function () {
    $docBlock = <<<'DOC'
    /**
     * @var array{
     *   key: string
     * }
     */
    DOC;

    expect(Regexes::findFirstVarDeclaration($docBlock))
        ->toBe('array{ key: string }');
});

test('multiline declarations may nest and carry trailing commas', function () {
    $docBlock = <<<'DOC'
    /**
     * @param array{
     *   nested: array{
     *     deep: bool,
     *   },
     *   list: list<string>,
     * } $input
     * @return array{
     *   id: non-empty-string,
     *   tags: list<
     *     non-empty-string
     *   >,
     * }
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBe('array{ nested: array{ deep: bool, }, list: list<string>, }')
        ->and(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBe('array{ id: non-empty-string, tags: list< non-empty-string >, }');
});

test('multiline declarations remain parsable', function () {
    $docBlock = <<<'DOC'
    /**
     * @param array{
     *   key: string,
     *   nested: array{
     *     deep: bool,
     *   },
     * } $input
     * @return array{
     *   id: string,
     * }
     */
    DOC;

    $parser = new TypeParser();

    expect((string) $parser->parse(Regexes::findParamWithNameDeclaration($docBlock, 'input')))
        ->toBe('array{key: string, nested: array{deep: bool}}')
        ->and((string) $parser->parse(Regexes::findReturnTypeDeclaration($docBlock)))
        ->toBe('array{id: string}');
});

test('a multiline declaration does not bleed into the following tag', function () {
    $docBlock = <<<'DOC'
    /**
     * Creates something.
     *
     * @param array{
     *   key: string
     * } $input
     * @param non-empty-string $name
     * @param int $count
     * @return array{
     *   id: string
     * }
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBe('array{ key: string }')
        ->and(Regexes::findParamWithNameDeclaration($docBlock, 'name'))
        ->toBe('non-empty-string')
        ->and(Regexes::findParamWithNameDeclaration($docBlock, 'count'))
        ->toBe('int')
        ->and(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBe('array{ id: string }');
});

test('descriptions are not part of the type', function () {
    $docBlock = <<<'DOC'
    /**
     * @param array{
     *   key: string
     * } $input The input of the operation.
     * @return non-empty-string The identifier.
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBe('array{ key: string }')
        ->and(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBe('non-empty-string');
});

test('single line declarations do not leak the closing comment delimiter', function () {
    expect(Regexes::findFirstVarDeclaration('/** @var array{id: string} */'))
        ->toBe('array{id: string}')
        ->and(Regexes::findReturnTypeDeclaration('/** @return array{id: string} */'))
        ->toBe('array{id: string}')
        ->and(Regexes::findParamWithNameDeclaration('/** @param array{id: string} $input */', 'input'))
        ->toBe('array{id: string}');
});

test('param names must match exactly', function () {
    $docBlock = <<<'DOC'
    /**
     * @param array{
     *   key: string
     * } $inputData
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBeNull()
        ->and(Regexes::findParamWithNameDeclaration($docBlock, 'inputData'))
        ->toBe('array{ key: string }');
});

test('unions and variadics are handled at the top level', function () {
    $docBlock = <<<'DOC'
    /**
     * @param array{
     *   key: string
     * }|null $input
     * @param non-empty-string ...$rest
     * @return array{a: int}
     *   | array{b: int}
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBe('array{ key: string }|null')
        ->and(Regexes::findParamWithNameDeclaration($docBlock, 'rest'))
        ->toBe('non-empty-string')
        ->and(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBe('array{a: int} | array{b: int}');
});

test('missing declarations return null', function () {
    $docBlock = <<<'DOC'
    /**
     * Just a description, nothing else.
     */
    DOC;

    expect(Regexes::findParamWithNameDeclaration($docBlock, 'input'))
        ->toBeNull()
        ->and(Regexes::findReturnTypeDeclaration($docBlock))
        ->toBeNull()
        ->and(Regexes::findFirstVarDeclaration($docBlock))
        ->toBeNull();
});
