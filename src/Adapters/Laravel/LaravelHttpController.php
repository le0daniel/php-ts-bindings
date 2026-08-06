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
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Client\OperationSPAClient;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidOutputException;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
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
            // The whole chain, oldest first. On an ordinary error that is the one exception the
            // application threw; when presenting it failed too, reporting only the cause would
            // hide the failure that actually needs fixing.
            foreach ($result->throwableChain() as $throwable) {
                $this->exceptionHandler->report($throwable);
            }
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

        return count($inputData) === 0 ? null : $inputData;
    }

    /**
     * getTraceAsString() rather than getTrace(): the latter carries the actual call arguments, and
     * a pure enum among them makes json_encode() refuse the whole response - which turned debug
     * mode into a 500 of its own. The string form is a full stack trace, always encodable, and does
     * not put argument values on the wire.
     *
     * @return array<string, mixed>
     */
    private static function describeThrowable(Throwable $throwable): array
    {
        return Dicts::filterNullValues([
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'issues' => $throwable instanceof InvalidOutputException ? $throwable->issues->serializeToDebugFields() : null,
        ]);
    }

    private function produceJsonResponse(RpcResult $result): JsonResponse
    {
        $jsonResponse = $result->jsonSerialize();
        if (!$this->debug) {
            return new JsonResponse($jsonResponse, status: $result->statusCode);
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
            $jsonResponse['__debug'] = Dicts::filterNullValues([
                ...self::describeThrowable($result->cause),
                // Only ever set when handling one failure produced another, so filterNullValues
                // keeps it out of the response on every ordinary error.
                'previous' => count($result->previous) > 0
                    ? array_map(self::describeThrowable(...), $result->previous)
                    : null,
            ]);
        }

        return new JsonResponse(
            $jsonResponse,
            status: $result->statusCode
        );
    }
}