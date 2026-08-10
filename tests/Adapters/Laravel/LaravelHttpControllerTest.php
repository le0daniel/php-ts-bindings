<?php

namespace Tests\Adapters\Laravel;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ClientFactory;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\Adapters\Laravel\OperationClientFactory;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Adapters\PsrContainerAdapter;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Server;
use Mockery;
use Throwable;
use TypeError;

test('handle successful http query request', function () {
    // Arrange
    $fcn = 'docs.method';
    $inputData = ['name' => 'some_value'];

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Request::create('/query/docs.method', 'GET', $inputData);

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
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
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

    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);

    $server = new Server($operationRegistry, new PsrContainerAdapter(container: $app));

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

test('an operations-spa request gets the client directives appended', function () {
    // Arrange
    $fcn = 'docs.method';
    $inputData = ['name' => 'some_value'];

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Request::create('/query/docs.method', 'GET', $inputData);

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
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
        public function someMethod(array $input, null $context, Client $client): array
        {
            $client->success('Saved');
            $client->redirect('/docs/123', true);

            return ['id' => '123', 'name' => $input['name']];
        }
    };

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);

    $request->headers->set(OperationClientFactory::CLIENT_ID_HEADER, 'operations-spa');
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);

    $controller = new LaravelHttpController(
        new Server($operationRegistry, new PsrContainerAdapter(container: $app)),
        $exceptionHandler,
        null,
    );

    // Act
    $response = $controller->handleHttpQueryRequest($fcn, $request);

    // Assert
    expect($response->getData(true))->toEqual([
        'success' => true,
        'data' => ['id' => '123', 'name' => 'some_value'],
        '__client' => [
            'redirect' => ['url' => '/docs/123', 'reload' => true],
            'toasts' => [
                ['type' => 'success', 'message' => 'Saved'],
            ],
            'type' => 'operations-spa',
        ],
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
    $request = Request::create('/query/docs.method', 'GET', $inputData);

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
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
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

    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);
    $repository->shouldReceive('get')->with('app.debug')->andReturn(false);

    $server = new Server($operationRegistry, new PsrContainerAdapter(container: $app));

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
                'fields' => [
                    '__root' => ['validation.missing_property'],
                ],
            ],
            'code' => 422,
            'type' => 'INVALID_INPUT',
        ]);
});
test('a nested query parameter comes back as an RpcError rather than escaping as a TypeError', function () {
    // ?filter[a]=1 hands back a nested array. A string typed callback raised a TypeError here,
    // before Server::query() was reached, so it bypassed the guarantee that every Throwable comes
    // back as an RpcError and produced a raw framework 500. The generated client never emits nested
    // params, but a hand written one, a bookmarked URL or a crawler will.
    $fcn = 'docs.method';

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Request::create('/query/docs.method', 'GET', ['name' => ['nested' => '1']]);

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
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
        public function someMethod(array $input, null $context, Client $client): array
        {
            return ['id' => '123', 'name' => $input['name']];
        }
    };

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);
    $exceptionHandler->shouldReceive('report')->andReturnNull();

    $server = new Server($operationRegistry, new PsrContainerAdapter(container: $app));
    $response = new LaravelHttpController($server, $exceptionHandler, null)
        ->handleHttpQueryRequest($fcn, $request);

    // The schema rejects it, which is a 422 and not an unhandled TypeError.
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['type'])->toBe('INVALID_INPUT');
});

/**
 * A stale middleware class name is the case the previous chain exists for: the name fails
 * assertIsMiddleware, and then reflecting the same name to work out what the operation exposes
 * fails too. Two failures, and the one that decided the response is the second.
 *
 * @return array{LaravelHttpController, Request, string, Closure(): list<Throwable>}
 */
function staleMiddlewareController(bool $debug): array
{
    $fcn = 'docs.method';
    $reported = [];

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Request::create('/query/docs.method', 'GET', ['name' => 'some_value']);

    $operationDefinition = new Definition(
        OperationType::QUERY,
        'MyClass',
        'someMethod',
        'method',
        'docs',
        ['Tests\Adapters\Laravel\DoesNotExistMiddleware'],
    );

    $operation = new Operation(
        'somekey',
        $operationDefinition,
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);
    $exceptionHandler->shouldReceive('report')->andReturnUsing(function (Throwable $throwable) use (&$reported): void {
        $reported[] = $throwable;
    });

    $controller = new LaravelHttpController(
        new Server($operationRegistry, new PsrContainerAdapter(container: $app)),
        $exceptionHandler,
        null,
        debug: $debug,
    );

    // By reference on purpose: the reports only land once the request below is handled.
    return [$controller, $request, $fcn, static function () use (&$reported): array {
        return $reported;
    }];
}

test('an ordinary error carries no previous key in debug mode', function () {
    $fcn = 'docs.method';

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Request::create('/query/docs.method', 'GET', ['none' => 'value']);

    $operationDefinition = new Definition(OperationType::QUERY, 'MyClass', 'someMethod', 'method', 'docs', []);
    $operation = new Operation(
        'somekey',
        $operationDefinition,
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
        public function someMethod(array $input, null $context, Client $client): array
        {
            return ['id' => '123', 'name' => $input['name']];
        }
    };

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);
    $exceptionHandler->shouldReceive('report')->andReturnNull();

    $response = new LaravelHttpController(
        new Server($operationRegistry, new PsrContainerAdapter(container: $app)),
        $exceptionHandler,
        null,
        debug: true,
    )->handleHttpQueryRequest($fcn, $request);

    expect($response->getData(true)['__debug'])->not->toHaveKey('previous');
});

test('directives queued before a failure never reach the client', function () {
    // The reason RpcError holds no Client: a handler that toasts "Saved" and then throws would
    // otherwise have the browser announce work that did not happen. Whatever the client collected
    // before the failure is dropped with the request.
    $fcn = 'docs.method';

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    $request = Request::create('/query/docs.method', 'GET', ['name' => 'some_value']);
    $request->headers->set(OperationClientFactory::CLIENT_ID_HEADER, 'operations-spa');

    $operationDefinition = new Definition(OperationType::QUERY, 'MyClass', 'someMethod', 'method', 'docs', []);
    $operation = new Operation(
        'somekey',
        $operationDefinition,
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
        public function someMethod(array $input, null $context, Client $client): array
        {
            $client->success('Saved');
            $client->redirect('/docs/123');
            $client->invalidate('docs');

            throw new RuntimeException('the save did not happen after all');
        }
    };

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);
    $exceptionHandler->shouldReceive('report')->andReturnNull();

    $response = new LaravelHttpController(
        new Server($operationRegistry, new PsrContainerAdapter(container: $app)),
        $exceptionHandler,
        null,
    )->handleHttpQueryRequest($fcn, $request);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toEqual([
            'success' => false,
            'code' => 500,
            'type' => 'INTERNAL_ERROR',
        ]);
});

test('a custom client factory decides the client, not the header', function () {
    $fcn = 'docs.method';

    $typeParser = new TypeParser();
    $operationRegistry = Mockery::mock(OperationRegistry::class);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $app = Mockery::mock(Application::class);
    // No X-Client-Id header: the default factory would pick the NullClient here.
    $request = Request::create('/query/docs.method', 'GET', ['name' => 'some_value']);

    $operationDefinition = new Definition(OperationType::QUERY, 'MyClass', 'someMethod', 'method', 'docs', []);
    $operation = new Operation(
        'somekey',
        $operationDefinition,
        fn () => $typeParser->parse('array{name: string}'),
        fn () => $typeParser->parse('array{id: string, name: string}'),
    );

    $controllerInstance = new class () {
        public function someMethod(array $input, null $context, Client $client): array
        {
            $client->success('Saved');

            return ['id' => '123', 'name' => $input['name']];
        }
    };

    $operationRegistry->shouldReceive('has')->with(OperationType::QUERY, $fcn)->andReturn(true);
    $operationRegistry->shouldReceive('get')->with(OperationType::QUERY, $fcn)->andReturn($operation);
    $app->shouldReceive('get')->with($operationDefinition->fullyQualifiedClassName)->andReturn($controllerInstance);

    $clientFactory = new class () implements ClientFactory {
        public function createClientFromHttpRequest(Request $request): Client
        {
            return new OperationSPAClient();
        }
    };

    $response = new LaravelHttpController(
        new Server($operationRegistry, new PsrContainerAdapter(container: $app)),
        $exceptionHandler,
        null,
        clientFactory: $clientFactory,
    )->handleHttpQueryRequest($fcn, $request);

    expect($response->getData(true))->toEqual([
        'success' => true,
        'data' => ['id' => '123', 'name' => 'some_value'],
        '__client' => [
            'toasts' => [
                ['type' => 'success', 'message' => 'Saved'],
            ],
            'type' => 'operations-spa',
        ],
    ]);
});
