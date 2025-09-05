<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades;
use JsonSerializable;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Throwable;

readonly class LaravelHttpController
{
    public const string QUERY_NAME = '__query_route';
    public const string COMMAND_NAME = '__command_route';
    public const string CLIENT_ID_HEADER = 'X-Client-Id';

    public function __construct(
        private Server           $server,
        private ExceptionHandler $exceptionHandler,
        private ?ContextFactory  $contextFactory,
        private bool             $debug = false,
    )
    {
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

    /**
     * @throws Throwable
     */
    public function handleHttpQueryRequest(string $fqn, Http\Request $request, Application $app): JsonResponse
    {
        $client = $this->createClient($request);
        $context = $this->createContext($request);

        $result = $this->server->query(
            $fqn,
            $this->gatherInputFromRequest('query', $request),
            $context,
            $client,
            $app
        );

        return $this->produceJsonResponse($result, $client);
    }

    /**
     * @throws Throwable
     */
    public function handleHttpCommandRequest(string $fqn, Http\Request $request, Application $app): JsonResponse
    {
        $client = $this->createClient($request);
        $context = $this->createContext($request);

        $result = $this->server->command(
            $fqn,
            $this->gatherInputFromRequest('query', $request),
            $context,
            $client,
            $app
        );

        return $this->produceJsonResponse($result, $client);
    }

    private function createClient(Http\Request $request): Client
    {
        if ($request->header(self::CLIENT_ID_HEADER) === 'operations-spa') {
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
                } catch (Throwable) {
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
     * @param array<string, mixed> $response
     * @param Client $client
     * @return array<string, mixed>
     */
    private function appendClientDirectives(array $response, Client $client): array
    {
        if (!$client instanceof JsonSerializable) {
            return $response;
        }

        $clientData = $client->jsonSerialize();
        if ($clientData === null) {
            return $response;
        }

        $response['__client'] = $clientData;
        return $response;
    }

    private function produceJsonResponse(RpcSuccess|RpcError $result, Client $client): JsonResponse
    {
        if ($result instanceof RpcSuccess) {
            return new JsonResponse(
                $this->appendClientDirectives([
                    'success' => true,
                    'data' => $result->data,
                ], $client),
                200
            );
        }

        $this->exceptionHandler->report($result->cause);
        $content = $this->appendClientDirectives([
            'success' => false,
            'code' => $result->code,
            'details' => $result->details
        ], $client);

        if ($this->debug) {
            $content['__debug'] = Arrays::filterNullValues([
                'class' => get_class($result->cause),
                'message' => $result->cause->getMessage(),
                'code' => $result->cause->getCode(),
                'file' => $result->cause->getFile(),
                'line' => $result->cause->getLine(),
                'trace' => $result->cause->getTrace(),
                'issues' => $result->cause instanceof Failure ? $result->cause->issues->serializeToDebugFields() : null,
            ]);
        }

        return new JsonResponse(
            $content,
            $result->code
        );
    }
}