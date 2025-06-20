<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades;
use Illuminate\Http;
use Illuminate\Routing\Route;
use Illuminate\Http\JsonResponse;
use Le0daniel\PhpTsBindings\BindingsManager;

final readonly class LaravelHttpController
{
    /**
     * @param BindingsManager<Http\Request, Http\Response, mixed> $bindingsManager
     */
    public function __construct(private BindingsManager $bindingsManager)
    {
    }

    public function handleHttpQueryRequest(string $fcn, Http\Request $request): JsonResponse
    {
        return $this->bindingsManager->executeQuery($fcn, $request);
    }

    public function handleHttpCommandRequest(string $fcn, Http\Request $request): JsonResponse
    {
        return $this->bindingsManager->executeCommand($fcn, $request);
    }

    // ToDo: Return type
    public static function registerQueries(string $routePrefix = 'query'): Route
    {
        return Facades\Route::get("{$routePrefix}/{fqn}", [self::class, 'handleHttpQueryRequest']);
    }

    // ToDo: Return type
    public static function registerCommands(string $routePrefix = 'command'): Route
    {
        return Facades\Route::post("{$routePrefix}/{fqn}", [self::class, 'handleHttpCommandRequest']);
    }
}