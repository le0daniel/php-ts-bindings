<?php

namespace Tests\Adapters\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\OperationDefinition;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Mockery;
use Symfony\Component\HttpFoundation\InputBag;

test('handle successful http query request', function () {
    // Arrange
    $fcn = 'docs.method';
    $inputData = ['name' => 'some_value'];

    $typeParser = new TypeParser();
    $repository = Mockery::mock(Repository::class);
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Mockery::mock(Request::class);
    $request->query = new InputBag($inputData);

    $operationDefinition = new OperationDefinition(
        'query',
        'MyClass',
        'someMethod',
        'method',
        'docs',
        'input',
        null,
        [],
        [],
    );

    $operation = new Operation(
        $operationDefinition,
        fn() => $typeParser->parse('array{name: string}'),
        fn() => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class() {
        public function __construct()
        {
        }

        public function someMethod(array $input, null $context, Client $client): array
        {
            return ['id' => '123', 'name' => $input['name']];
        }
    };

    $operationRegistry->shouldReceive('has')->with('query', $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with('query', $fcn)->andReturn($operation);

    $request->shouldReceive('header')->with(LaravelHttpController::CLIENT_ID_HEADER)->andReturnNull();
    $app->shouldReceive('make')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);

    $controller = new LaravelHttpController(
        $repository,
        $operationRegistry,
        new SchemaExecutor(),
        $exceptionHandler,
        null
    );

    // Act
    $response = $controller->handleHttpQueryRequest($fcn, $request, $app);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toEqual([
            'success' => true,
            'data' => ['id' => '123', 'name' => 'some_value'],
        ]);
});

test('handle invalid input http query request', function () {
    // Arrange
    $fcn = 'docs.method';
    $inputData = ['none' => 'value'];

    $typeParser = new TypeParser();
    $repository = Mockery::mock(Repository::class);
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Mockery::mock(Request::class);
    $request->query = new InputBag($inputData);

    $operationDefinition = new OperationDefinition(
        'query',
        'MyClass',
        'someMethod',
        'method',
        'docs',
        'input',
        null,
        [],
        [],
    );

    $operation = new Operation(
        $operationDefinition,
        fn() => $typeParser->parse('array{name: string}'),
        fn() => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class() {
        public function __construct()
        {
        }

        public function someMethod(array $input, null $context, Client $client): array
        {
            return ['id' => '123', 'name' => $input['name']];
        }
    };

    $operationRegistry->shouldReceive('has')->with('query', $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with('query', $fcn)->andReturn($operation);

    $request->shouldReceive('header')->with(LaravelHttpController::CLIENT_ID_HEADER)->andReturnNull();
    $app->shouldReceive('make')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);
    $repository->shouldReceive('get')->with('app.debug')->andReturn(false);

    $controller = new LaravelHttpController(
        $repository,
        $operationRegistry,
        new SchemaExecutor(),
        $exceptionHandler,
        null
    );

    // Act
    $response = $controller->handleHttpQueryRequest($fcn, $request, $app);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'data' => [
                '__root' => ['validation.missing_property']
            ],
            'type' => 'INVALID_INPUT',
            'code' => 422,
        ]);
});