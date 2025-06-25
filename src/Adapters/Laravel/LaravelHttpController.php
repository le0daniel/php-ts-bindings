<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Closure;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades;
use Illuminate\Http;
use Illuminate\Routing\Route;
use Illuminate\Http\JsonResponse;
use Le0daniel\PhpTsBindings\BindingsManager;
use Illuminate\Routing\Pipeline;

final readonly class LaravelHttpController
{
    /**
     * @param BindingsManager<Http\Request, Http\Response, mixed> $bindingsManager
     */
    public function __construct(
        private BindingsManager              $bindingsManager,
    )
    {
    }

    private function isDebugEnabled(): bool
    {
        return config('app.debug', false);
    }

    private function isDescriptionRequest(Http\Request $request): bool
    {
        return $request->header('X-Describe') === 'true';
    }

    private function handleDescriptionRequest(string $type, string $fcn, Http\Request $request): JsonResponse
    {
        if (!$this->config->get('app.debug', false)) {
            return new JsonResponse(['message' => 'Not allowed'], 403);
        }

        if (!$this->bindingsManager->operations->has($type, $fcn)) {
            return new JsonResponse(['message' => 'Not found'], 404);
        }

        $operation = $this->bindingsManager->operations->get($type, $fcn);
        $middlewares = $request->route()->gatherMiddleware();

        return new JsonResponse([
            'success' => true,
            'operation' => [
                'definition' => [
                    'type' => $operation->definition->type,
                    'fullyQualifiedClassName' => $operation->definition->fullyQualifiedClassName,
                    'methodName' => $operation->definition->methodName,
                    'name' => $operation->definition->name,
                    'namespace' => $operation->definition->namespace,
                    'inputParameterName' => $operation->definition->inputParameterName,
                    'description' => $operation->definition->description,
                    'caughtExceptions' => $operation->definition->caughtExceptions,
                ],
                'middlewares' => array_map(fn(mixed $type) => match (true) {
                    is_string($type) => $type,
                    $type instanceof Closure => "Closure()",
                    is_object($type) => get_class($type),
                    default => 'unknown',
                },$middlewares),
            ],
        ], 200);
    }

    public function handleHttpQueryRequest(string $fcn, Http\Request $request, Application $app): JsonResponse
    {
        if ($this->isDescriptionRequest($request)) {
            return $this->handleDescriptionRequest('query', $fcn, $request);
        }

        return $this->bindingsManager->executeQuery($fcn, $request);
    }

    public function handleHttpCommandRequest(string $fcn, Http\Request $request, Application $app): JsonResponse
    {
        if ($this->isDescriptionRequest($request)) {
            return $this->handleDescriptionRequest('command', $fcn, $request);
        }

        return $this->bindingsManager->executeCommand($fcn, $request);
    }

    private function handleWebRequest(string $type, string $fcn, Http\Request $request, Application $app): mixed
    {
        /** @var array<string, list<string>> $config */
        $config = $app->make('config')->get('bindings.http.middleware');
        [$namespace] = explode('.', $fcn, 2);

        $middlewares = $config[$namespace] ?? [];

        return new Pipeline($app)
            ->send($request)
            ->through($middlewares)
            ->then(function (Http\Request $request) use($fcn, $type) {
                if ($this->isDescriptionRequest($request)) {
                    return $this->handleDescriptionRequest('command', $fcn, $request);
                }

                return match ($type) {
                    'command' => $this->bindingsManager->executeCommand($fcn, $request),
                    'query' => $this->bindingsManager->executeQuery($fcn, $request),
                };
            });
    }

    // ToDo: Return type
    public static function registerQueries(string $routePrefix = 'query'): Route
    {
        return Facades\Route::get("{$routePrefix}/{fqn}", [self::class, 'handleHttpQueryRequest'])
            ->name('__bindings_query');
    }

    // ToDo: Return type
    public static function registerCommands(string $routePrefix = 'command'): Route
    {
        return Facades\Route::post("{$routePrefix}/{fqn}", [self::class, 'handleHttpCommandRequest'])
            ->name('__bindings_command');
    }
}