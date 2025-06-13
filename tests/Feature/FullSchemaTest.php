<?php

use Le0daniel\PhpTsBindings\Executor\Registry\CachedRegistry;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Tests\Feature\Mocks\CreateObjectInput;
use Tests\Feature\Mocks\CreateUserInput;

function prepare(string $type): Closure
{
    return static function (mixed $value) use ($type) {
        $optimizer = new ASTOptimizer();

        $ast = new TypeParser()->parse($type);
        $registryCode = $optimizer->generateOptimizedCode(['node' => $ast]);

        /** @var CachedRegistry $registry */
        $registry = eval("return {$registryCode};");
        $executor = new SchemaExecutor();
        $astResult = $executor->parse($ast, $value);
        $registryResult = $executor->parse($registry->get('node'), $value);

        expect($astResult)->toBeInstanceOf($registryResult::class)
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

    expect($execute([]))->toBeFailure();
    expect($execute(1))->toBeFailure();
    expect($execute(null))->toBeFailure();
    expect($execute([
        'name' => 'my name',
        'options' => []
    ]))->toBeFailure('validation.invalid_type');

    expect($execute([
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
    expect($result)->toBeSuccess();
    expect($result->value)->toBeInstanceOf(CreateObjectInput::class);
    expect($result->value->name)->toBe('my name');
    expect($result->value->options['type'])->toBe('square');
    expect($result->value->options['dimensions'])->toBe(10);

    $result = $execute([
        'name' => 'my name',
        'options' => [
            'type' => 'circle',
            'radius' => 10
        ]
    ]);
    expect($result)->toBeSuccess();
    expect($result->value)->toBeInstanceOf(CreateObjectInput::class);
    expect($result->value->name)->toBe('my name');
    expect($result->value->options['type'])->toBe('circle');
    expect($result->value->options['radius'])->toBe(10);
});

