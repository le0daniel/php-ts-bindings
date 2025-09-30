<?php

namespace Tests\Adapters\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Presenter\CatchAllPresenter;
use Le0daniel\PhpTsBindings\Server\Presenter\InvalidInputPresenter;
use Le0daniel\PhpTsBindings\Server\Server;
use Mockery;
use Symfony\Component\HttpFoundation\InputBag;

test('handle successful http query request', function () {
    // Arrange
    $fcn = 'docs.method';
    $inputData = ['name' => 'some_value'];

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Mockery::mock(Request::class);
    $request->query = new InputBag($inputData);

    $operationDefinition = new Definition(
        OperationType::QUERY,
        'MyClass',
        'someMethod',
        'method',
        'docs',
        [],
    );

    $operation = new Operation(
        'somekey',
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

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);

    $request->shouldReceive('header')->with(LaravelHttpController::CLIENT_ID_HEADER)->andReturnNull();
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);

    $server = new Server(
        $operationRegistry,
        [],
        new CatchAllPresenter(),
        $app,
    );

    $controller = new LaravelHttpController(
        $server,
        $exceptionHandler,
        null,
    );

    // Act
    $response = $controller->handleHttpQueryRequest($fcn, $request);

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

    $operationDefinition = new Definition(
        OperationType::QUERY,
        'MyClass',
        'someMethod',
        'method',
        'docs',
        [],
    );

    $operation = new Operation(
        'somekey',
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

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);
    $exceptionHandler->shouldReceive('report')->with(InvalidInputException::class);

    $request->shouldReceive('header')->with(LaravelHttpController::CLIENT_ID_HEADER)->andReturnNull();
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);
    $repository->shouldReceive('get')->with('app.debug')->andReturn(false);

    $server = new Server(
        $operationRegistry,
        [new InvalidInputPresenter()],
        new CatchAllPresenter(),
        $app,
    );

    $controller = new LaravelHttpController(
        $server,
        $exceptionHandler,
        null,
    );

    // Act
    $response = $controller->handleHttpQueryRequest($fcn, $request);

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'details' => [
                'type' => 'INVALID_INPUT',
                'fields' => [
                    '__root' => ['validation.missing_property']
                ],
            ],
            'code' => 422,
        ]);
});