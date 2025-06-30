<?php

use Illuminate\Support\Collection;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\Registry\CachedTypeRegistry;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Tests\Feature\Mocks\CreateObjectInput;
use Tests\Feature\Mocks\CreateUserInput;

function prepare(string $type, string $mode = 'parse'): Closure
{
    return static function (mixed $value) use ($type, $mode) {
        $optimizer = new ASTOptimizer();

        $ast = new TypeParser(
            consumers: TypeParser::defaultConsumers(collectionClasses: [Collection::class])
        )->parse($type);
        $registryCode = $optimizer->generateOptimizedCode(['node' => $ast]);

        /** @var CachedTypeRegistry $registry */
        $registry = eval("return {$registryCode};");
        $executor = new SchemaExecutor();

        /** @var Success|Failure $astResult */
        $astResult = $executor->{$mode}($ast, $value);
        $registryResult = $executor->{$mode}($registry->get('node'), $value);

        expect($astResult)->toBeInstanceOf($registryResult::class)
            ->and($astResult->issues->serializeToCompleteString())->toEqual($registryResult->issues->serializeToCompleteString())
            ->and(serialize($astResult))->toEqual(serialize($registryResult));

        return $astResult;
    };
}

test('Test Create User input schema', function () {
    $schema = prepare('string|int|' . CreateUserInput::class);

    expect($schema('my string value'))->toBeSuccess();
    expect($schema(-123))->toBeSuccess();
    expect($schema([
        'username' => 'my username',
        'age' => 123,
        'email' => 'my@mail.test',
    ]))->toBeSuccess();

    expect($schema([
        'username' => 'my username',
        'age' => 123,
        'email' => 'my mail',
    ]))->toBeFailureAt('email', 'validation.invalid_email');

    $createUser = $schema([
        'username' => 'my username',
        'age' => 123,
        'email' => 'my@mail.test',
    ])->value;
    expect($createUser)->toBeInstanceOf(CreateUserInput::class);
    expect($createUser->username)->toBe('my username');
    expect($createUser->age)->toBe(123);
    expect($createUser->email)->toBe('my@mail.test');
});

test("Create user input schema", function () {
    $execute = prepare(CreateObjectInput::class);

    expect($execute([]))->toBeFailure()
        ->and($execute(1))->toBeFailure()
        ->and($execute(null))->toBeFailure()
        ->and($execute([
            'name' => 'my name',
            'options' => []
        ]))->toBeFailure('validation.invalid_type')
        ->and($execute([
            'name' => 'my name',
            'options' => [
                'type' => 'square',
                'radius' => 10
            ]
        ]))->toBeFailure();

    $result = $execute([
        'name' => 'my name',
        'options' => [
            'type' => 'square',
            'dimensions' => 10
        ]
    ]);
    expect($result)->toBeSuccess()
        ->and($result->value)->toBeInstanceOf(CreateObjectInput::class)
        ->and($result->value->name)->toBe('my name')
        ->and($result->value->options['type'])->toBe('square')
        ->and($result->value->options['dimensions'])->toBe(10);

    $result = $execute([
        'name' => 'my name',
        'options' => [
            'type' => 'circle',
            'radius' => 10
        ]
    ]);
    expect($result)->toBeSuccess()
        ->and($result->value)->toBeInstanceOf(CreateObjectInput::class)
        ->and($result->value->name)->toBe('my name')
        ->and($result->value->options['type'])->toBe('circle')
        ->and($result->value->options['radius'])->toBe(10);
});

test('Execute parsing with custom collection class', function () {
    $collectionClass = Collection::class;
    $executor = prepare("\Illuminate\Support\Collection<int, array{id: string}>", 'parse');

    $validResult = $executor([
        ['id' => 'test'],
    ]);

    expect($validResult)->toBeSuccess()
        ->and($validResult->value)->toBeInstanceOf($collectionClass)
        ->and($validResult->value->first())->toEqual(['id' => 'test']);
});

test('Execute parsing with custom collection class as record', function () {
    $executor = prepare("\Illuminate\Support\Collection<string, int>", 'parse');

    $validResult = $executor(['id' => 123]);

    expect($validResult)->toBeSuccess()
        ->and($validResult->value)->toBeInstanceOf(Collection::class)
        ->and($validResult->value->toArray())->toEqual(['id' => 123]);
});

test('Execute serialization with custom record class', function () {
    $executor = prepare("\Illuminate\Support\Collection<string, int>", 'serialize');

    $validResult = $executor(['id' => 123]);

    expect($validResult)->toBeSuccess()
        ->and($validResult->value)->toBeInstanceOf(stdClass::class)
        ->and($validResult->value)->toEqual((object)['id' => 123]);
});

test('serialization with custom collection class', function () {
    $executor = prepare("\Illuminate\Support\Collection<int, array{id: string}>", 'serialize');

    $validResult = $executor(new Collection([
        ['id' => 'test'],
    ]));
    expect($validResult)->toBeSuccess();
});

test('serialization with null boundires', function () {
    $executor = prepare('list<(array{name: string}|null)>', 'serialize');

    $validResult = $executor([
        ['name' => 'leo'],
        null
    ]);
    expect($validResult)->toBeSuccess()
        ->and($validResult->value)->toEqual([
            (object)['name' => 'leo'],
            null
        ]);

    $invalidResult = $executor([
        ['name' => null],
        null
    ]);
    expect($invalidResult)->toBeSuccess()
        ->and($invalidResult->value)->toEqual([
            null,
            null
        ])
        ->and($executor("string"))->toBeFailure();

});