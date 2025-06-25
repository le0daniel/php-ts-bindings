<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Closure;
use Illuminate\Http\JsonResponse;
use JsonSerializable;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Contracts\ExecutionAdapter;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * @template C of object
 * @implements ExecutionAdapter<Http\Request, JsonResponse, C>
 */
final class LaravelHttpRequestAdapter implements ExecutionAdapter
{

    /**
     * @param Closure(Http\Request): C|null $contextFactory
     */
    public function __construct(private(set) readonly ?Closure $contextFactory = null)
    {
    }

    /**
     * @param Http\Request $request
     * @return C
     */
    public function createContext(mixed $request): mixed
    {
        if ($this->contextFactory) {
            return ($this->contextFactory)($request);
        }

        return null;
    }

    public function getInputFromRequest(string $type, mixed $request): mixed
    {
        return match ($type) {
            'query' => array_map(function(string $value): mixed {
                try {
                    return json_decode($value, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable $exception) {
                    return $value;
                }
            },$request->query->all()),
            'command' => $request->json()?->all() ?? null,
        };
    }

    public function invokeEndpoint(OperationDefinition $definition, mixed $input, mixed $context): mixed
    {
        $paramValues = $definition->inputParameterName ? [
            $definition->inputParameterName => $input,
        ] : [];

        $parameters = new ReflectionMethod($definition->fullyQualifiedClassName, $definition->methodName)->getParameters();
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $parameterClassName = $type->getName();
            if ($context instanceof $parameterClassName) {
                $paramValues[$parameter->getName()] = $context;
                break;
            }
        }

        return \Illuminate\Support\Facades\App::call(
            "{$definition->fullyQualifiedClassName}@{$definition->methodName}",
            $paramValues
        );
    }

    public function produceInvalidInputResponse(Failure $failure, mixed $context): JsonResponse
    {
        $debug = config('app.debug', false);
        // ToDo: Add debug info
        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'message' => 'Invalid input',
            'fields' => $failure->issues->serializeToFieldsArray(),
            'debug' => $debug ? $failure->issues->serializeToDebugFields() : null,
        ]), 422);
    }

    public function produceOperationNotFoundResponse(string $type, string $fullyQualifiedOperationName, mixed $request): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Not found.',
        ], 404);
    }

    public function produceInternalErrorResponse(Throwable $throwable, mixed $context): JsonResponse
    {
        $debug = config('app.debug', false);
        // ToDo: Remove exposing of throwable here.
        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'message' => 'Internal server error.',
            'exception' => $debug ? [
                'class' => get_class($throwable),
                'message' => $throwable->getMessage(),
                'code' => $throwable->getCode(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTrace(),
            ] : null,
        ]), 500);
    }

    public function produceClientAwareErrorResponse(ClientAwareException $exception, mixed $context): JsonResponse
    {
        $debug = config('app.debug', false);

        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'message' => 'Internal error.',
            'code' => $exception::code(),
            'name' => $exception::name(),
            'error' => $exception->serializeToResult(),
            'exception' => $debug ? [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ] : null,
        ]), 500);
    }

    public function produceResponse(Success|Failure $result, mixed $context): JsonResponse
    {
        $serializedContext = $context instanceof JsonSerializable ? $context->jsonSerialize() : $context;

        if ($result instanceof Success) {
            return new JsonResponse(Arrays::filterNullValues([
                'success' => $result instanceof Success,
                'data' => $result->value,
                'issues' => $result->issues->isEmpty() ? null : $result->issues->serializeToFieldsArray(),
                'context' => $serializedContext,
            ]), 200);
        }

        $debug = config('app.debug', false);

        return new JsonResponse(Arrays::filterNullValues([
            'success' => false,
            'message' => 'Invalid output data. Serialization failed.',
            'issues' => $result->issues->serializeToFieldsArray(),
            'context' => $serializedContext,
            'debug' => $debug ? $result->issues->serializeToDebugFields() : null,
        ]), 500);
    }
}