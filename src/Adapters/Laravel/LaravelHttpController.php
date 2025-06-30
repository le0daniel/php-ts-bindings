<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades;
use JsonSerializable;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\ActionClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\NullClient;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final readonly class LaravelHttpController
{
    public const string QUERY_NAME = '__query_route';
    public const string COMMAND_NAME = '__command_route';

    public function __construct(
        private ConfigRepository        $config,
        private OperationRegistry $operationRegistry,
        private SchemaExecutor    $executor,
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
        if ($request->header('X-Client-Id') === 'operations') {
            return new ActionClient();
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

    /**
     * @param Operation $operation
     * @param Success $input
     * @param Client $client
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private function createParameters(Operation $operation, Success $input, Client $client): array
    {
        $parameters = [
            $operation->definition->inputParameterName => $input->value,
        ];
        $reflection = new ReflectionMethod($operation->definition->fullyQualifiedClassName, $operation->definition->methodName);
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            if (is_a($type->getName(), Client::class, true)) {
                $parameters[$parameter->getName()] = $client;
                continue;
            }

            // ToDo: Create Context.
        }
        return $parameters;
    }

    /**
     * @param 'command'|'query' $type
     * @param string $fcn
     * @param Http\Request $request
     * @param Application $app
     * @return mixed
     * @throws ReflectionException
     */
    private function handleWebRequest(string $type, string $fcn, Http\Request $request, Application $app): mixed
    {
        if (!$this->operationRegistry->has($type, $fcn)) {
            return new JsonResponse(['error' => "Not found"], 404);
        }

        $operation = $this->operationRegistry->get($type, $fcn);
        $inputData = $this->gatherInputFromRequest($type, $request);
        $client = $this->createClient($request);

        // ToDO: catch catchable errors.
        return new Pipeline($app)
            ->send($request)
            ->through($operation->definition->middleware)
            ->then(function (Http\Request $request) use ($inputData, $operation, $app, $client) {
                $input = $this->executor->parse($operation->inputNode(), $inputData);
                if ($input instanceof Failure) {
                    return $this->produceInvalidInputResponse($input);
                }

                /** @var mixed $result */
                $result = $app->call(
                    [$operation->definition->fullyQualifiedClassName, $operation->definition->methodName],
                    $this->createParameters($operation, $input, $client),
                );

                $response = $this->executor->serialize($operation->outputNode(), $result);
                if ($response instanceof Failure) {
                    // ToDo: probably throw
                }

                return new JsonResponse(Arrays::filterNullValues([
                    'success' => true,
                    'data' => $response,
                    '__client' => $client instanceof JsonSerializable ? $client->jsonSerialize() : null,
                ]), 200);
            });
    }

    private function produceInvalidInputResponse(Failure $failure): JsonResponse
    {
        $isDebugEnabled = $this->config->get('app.debug');
        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'type' => 'INVALID_INPUT',
            'data' => $failure->issues->serializeToFieldsArray(),
            'debug' => $isDebugEnabled ? $failure->issues->serializeToDebugFields() : null,
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