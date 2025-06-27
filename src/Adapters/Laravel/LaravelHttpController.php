<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Attributes\Middleware;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Throwable;

final readonly class LaravelHttpController
{
    public const string QUERY_NAME = '__query_route';
    public const string COMMAND_NAME = '__command_route';

    public function __construct(
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

    /**
     * @param OperationDefinition $definition
     * @return array<class-string>
     * @throws ReflectionException
     */
    private function collectMiddleware(OperationDefinition $definition): array
    {
        $reflectionClass = new ReflectionClass($definition->fullyQualifiedClassName);
        $attributes = [
            ... $reflectionClass->getAttributes(Middleware::class),
            ... $reflectionClass->getMethod($definition->methodName)->getAttributes(Middleware::class),
        ];

        return empty($attributes) ? [] : array_reduce($attributes, function (array $carry, ReflectionAttribute $attribute) {
            $instance = $attribute->newInstance();
            array_push($carry, ...$instance->middleware);
            return $carry;
        }, []);
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
        $middlewares = $this->collectMiddleware($operation->definition);

        // ToDO: catch catchable errors.
        return new Pipeline($app)
            ->send($request)
            ->through($middlewares)
            ->then(function (Http\Request $request) use ($type, $operation) {
                $inputData = match ($type) {
                    'query' => array_map(static function (string $value): mixed {
                        try {
                            return json_decode($value, flags: JSON_THROW_ON_ERROR);
                        } catch (Throwable $exception) {
                            return $value;
                        }
                    }, $request->query->all()),
                    'command' => $request->json()->all(),
                };

                $input = $this->executor->parse($operation->inputNode(), $inputData);


                return $request;
            });
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