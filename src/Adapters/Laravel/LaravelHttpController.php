<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades;
use JsonSerializable;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\NullClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;
use Le0daniel\PhpTsBindings\Adapters\Laravel\MiddlewarePipeline\MiddlewarePipeline;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Contracts\ExposesClientData;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Throwable;

final readonly class LaravelHttpController
{
    public const string QUERY_NAME = '__query_route';
    public const string COMMAND_NAME = '__command_route';
    public const string CLIENT_ID_HEADER = 'X-Client-Id';

    public function __construct(
        private ConfigRepository  $config,
        private OperationRegistry $operationRegistry,
        private SchemaExecutor    $executor,
        private ExceptionHandler  $exceptionHandler,
        private ?ContextFactory   $contextFactory,
    )
    {
    }

    public function handleHttpQueryRequest(string $fcn, Http\Request $request, Application $app): JsonResponse
    {
        return $this->handleWebRequest('query', $fcn, $request, $app);
    }

    public function handleHttpCommandRequest(string $fcn, Http\Request $request, Application $app): JsonResponse
    {
        return $this->handleWebRequest('command', $fcn, $request, $app);
    }

    private function createClient(Http\Request $request): Client
    {
        if ($request->header(self::CLIENT_ID_HEADER) === 'operations') {
            return new OperationSPAClient();
        }

        return new NullClient();
    }

    /**
     * @param 'command'|'query' $type
     * @param Request $request
     * @return array<int|string, mixed>
     */
    private function gatherInputFromRequest(string $type, Http\Request $request): array
    {
        return match ($type) {
            'query' => array_map(static function (string $value): mixed {
                try {
                    return json_decode($value, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable $exception) {
                    return $value;
                }
            }, $request->query->all()),
            'command' => $request->json()->all(),
        };
    }

    private function createContext(Http\Request $request): mixed
    {
        return $this->contextFactory?->createContextFromHttpRequest($request);
    }

    /**
     * @param 'command'|'query' $type
     * @param string $fcn
     * @param Http\Request $request
     * @param Application $app
     * @return mixed
     * @throws BindingResolutionException
     */
    private function handleWebRequest(string $type, string $fcn, Http\Request $request, Application $app): mixed
    {
        if (!$this->operationRegistry->has($type, $fcn)) {
            return new JsonResponse(['error' => "Not found"], 404);
        }

        $operation = $this->operationRegistry->get($type, $fcn);

        $input = $this->executor->parse($operation->inputNode(), $this->gatherInputFromRequest($type, $request));
        if ($input instanceof Failure) {
            return $this->produceInvalidInputResponse($input);
        }

        // Create execution needs based on Definition and Request.
        $client = $this->createClient($request);
        $context = $this->createContext($request);
        $controllerClass = $app->make($operation->definition->fullyQualifiedClassName);
        $pipeline = new MiddlewarePipeline($app, $operation->definition->middleware);

        try {
            /** @var Success $result */
            $result = $pipeline->execute($input, $context, $client, function (mixed $input, mixed $context, Client $client) use ($controllerClass, $operation) {
                $result = $controllerClass->{$operation->definition->methodName}($input, $context, $client);
                $response = $this->executor->serialize($operation->outputNode(), $result);

                // We throw, so that middleware transactions need to handle invalid output.
                if ($response instanceof Failure) {
                    throw $response;
                }

                return $response;
            });

            return new JsonResponse(Arrays::filterNullValues([
                'success' => true,
                'data' => $result->value,
                '__client' => $client instanceof JsonSerializable ? $client->jsonSerialize() : null,
            ]), 200);
        } catch (Throwable $exception) {
            $this->exceptionHandler->report($exception);
            return $this->produceExceptionResponse($exception, $operation, $client);
        }
    }

    private function produceExceptionResponse(Throwable $exception, Operation $operation, Client $client): JsonResponse
    {
        $isDebugEnables = $this->config->get('app.debug');

        if ($exception instanceof Failure) {
            return new JsonResponse(Arrays::filterNullValues([
                'success' => false,
                'message' => 'Internal server error.',
                '__debug' => $isDebugEnables ? $exception->issues->serializeToDebugFields() : null,
                '__client' => $client instanceof JsonSerializable ? $client->jsonSerialize() : null,
            ]), 500);
        }

        $debugData = $isDebugEnables ? [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
        ] : null;

        if ($exception instanceof ClientAwareException && in_array($exception::class, $operation->definition->caughtExceptions, true)) {
            return new JsonResponse(Arrays::filterNullValues([
                'success' => false,
                'type' => $exception::type(),
                'data' => $exception instanceof ExposesClientData ? $exception->serializeToResult() : null,
                '__debug' => $debugData,
                '__client' => $client instanceof JsonSerializable ? $client->jsonSerialize() : null,
            ]), $exception::code());
        }

        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'message' => 'Internal server error.',
            '__client' => $client instanceof JsonSerializable ? $client->jsonSerialize() : null,
            '__debug' => $debugData,
        ]), 500);
    }

    private function produceInvalidInputResponse(Failure $failure): JsonResponse
    {
        $isDebugEnabled = $this->config->get('app.debug');
        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'type' => 'INVALID_INPUT',
            'data' => $failure->issues->serializeToFieldsArray(),
            '__debug' => $isDebugEnabled ? $failure->issues->serializeToDebugFields() : null,
        ]), 422);
    }

    public static function registerQueries(string $routePrefix = 'query'): Route
    {
        return Facades\Route::get("{$routePrefix}/{fqn}", [self::class, 'handleHttpQueryRequest'])
            ->name(self::QUERY_NAME);
    }

    public static function registerCommands(string $routePrefix = 'command'): Route
    {
        return Facades\Route::post("{$routePrefix}/{fqn}", [self::class, 'handleHttpCommandRequest'])
            ->name(self::COMMAND_NAME);
    }
}