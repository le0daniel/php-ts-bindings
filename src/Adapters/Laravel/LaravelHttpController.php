<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\RpcResult;
use Le0daniel\PhpTsBindings\Contracts\SerializableClient;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidOutputException;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Utils\Dicts;
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
    public function handleHttpQueryRequest(string $fqn, Http\Request $request): JsonResponse
    {
        return $this->server->query(
                $fqn,
                input: $this->gatherInputFromRequest(OperationType::QUERY, $request),
                context: $this->contextFactory?->createContextFromHttpRequest($request),
                client: $this->createClient($request),
            )
                |> $this->reportExceptions(...)
                |> $this->produceJsonResponse(...);
    }

    /**
     * @throws Throwable
     */
    public function handleHttpCommandRequest(string $fqn, Http\Request $request): JsonResponse
    {
        return $this->server->command(
                $fqn,
                input: $this->gatherInputFromRequest(OperationType::COMMAND, $request),
                context:$this->contextFactory?->createContextFromHttpRequest($request),
                client: $this->createClient($request),
            )
                |> $this->reportExceptions(...)
                |> $this->produceJsonResponse(...);
    }

    private function reportExceptions(RpcResult $result): RpcResult
    {
        if ($result instanceof RpcError) {
            $this->exceptionHandler->report($result->cause);
        }
        return $result;
    }

    private function createClient(Http\Request $request): Client
    {
        if ($request->header(self::CLIENT_ID_HEADER) === 'operations-spa') {
            return new OperationSPAClient();
        }

        return new NullClient();
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function gatherInputFromRequest(OperationType $type, Http\Request $request): ?array
    {
        $inputData = match ($type) {
            // mixed, not string: ?filter[a]=1 hands back a nested array, and a string parameter
            // raised a TypeError here - before Server::query() was reached, so it escaped the
            // guarantee that every Throwable comes back as an RpcError. Anything that is not a
            // string is passed through untouched for the schema to reject properly.
            OperationType::QUERY => array_map(static function (mixed $value): mixed {
                if (!is_string($value)) {
                    return $value;
                }

                try {
                    return json_decode($value, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    return $value;
                }
            }, $request->query->all()),
            OperationType::COMMAND => $request->json()->all(),
        };

        return empty($inputData) ? null : $inputData;
    }

    private function produceJsonResponse(RpcResult $result): JsonResponse
    {
        $httpStatusCode = match (true) {
            $result instanceof RpcSuccess => 200,
            $result instanceof RpcError => $result->type->value,
            default => throw new \RuntimeException('Unexpected result type'),
        };

        $jsonResponse = $result->jsonSerialize();
        if (!$this->debug) {
            return new JsonResponse($jsonResponse, status: $httpStatusCode);
        }

        // We append some general debug information
        if ($result->resolveInfo) {
            $jsonResponse['__resolveInfo'] = [
                "handler" => "{$result->resolveInfo->className}@{$result->resolveInfo->methodName}",
                "middleware" => $result->resolveInfo->middleware,
                "fqn" => $result->resolveInfo->fullyQualifiedName,
                "type" => $result->resolveInfo->operationType->name,
            ];
        }

        // We append debug info for failed operations
        if ($result instanceof RpcError) {
            $exception = $result->cause;
            $jsonResponse['__debug'] = Dicts::filterNullValues([
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
                'issues' => $exception instanceof InvalidOutputException ? $exception->issues->serializeToDebugFields() : null,
            ]);
        }

        return new JsonResponse(
            $jsonResponse,
            status: $httpStatusCode
        );
    }
}