<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ConfigurableMiddleware;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Contracts\ServerAdapter;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Server\Adapters\NewInstanceAdapter;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidMiddlewareException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidOutputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\MiddlewareDefinition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Errors\ErrorClassifier;
use Le0daniel\PhpTsBindings\Server\Errors\ExceptionScope;
use Le0daniel\PhpTsBindings\Server\Errors\ThrowAttributeResolver;
use Le0daniel\PhpTsBindings\Server\Pipeline\ContextualPipeline;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use ReflectionException;
use Throwable;

final readonly class Server
{
    public SchemaExecutor $executor;

    /**
     * Error categorisation is not an extension point: the catalogue is finite and the server needs
     * it to run. What an application configures is which of its exceptions belong in which
     * category, via ServerConfiguration::withExceptions(). Everything unrecognised is an internal
     * error - an exception only reaches the client on purpose, never by accident.
     */
    private ErrorClassifier $classifier;

    public function __construct(
        public OperationRegistry   $registry,
        private ServerAdapter      $adapter = new NewInstanceAdapter(),
        public ServerConfiguration $configuration = new ServerConfiguration(),
    ) {
        $this->executor = new SchemaExecutor();
        $this->classifier = new ErrorClassifier(
            authenticationExceptions: $configuration->unauthenticatedExceptions,
            authorizationExceptions: $configuration->unauthorizedExceptions,
            notFoundExceptions: $configuration->notFoundExceptions,
            rateLimitedExceptions: $configuration->rateLimitedExceptions,
        );
    }

    public function query(string $key, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        if (!$this->registry->has(OperationType::QUERY, $key)) {
            return $this->present(
                new OperationNotFoundException("Operation with key: {$key} was not found."),
                null,
            );
        }

        return $this->execute($this->registry->get(OperationType::QUERY, $key), $input, $context, $client);
    }

    public function command(string $key, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        if (!$this->registry->has(OperationType::COMMAND, $key)) {
            return $this->present(
                new OperationNotFoundException("Operation with key: {$key} was not found."),
                null,
            );
        }

        return $this->execute($this->registry->get(OperationType::COMMAND, $key), $input, $context, $client);
    }

    private function execute(Operation $operation, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        $resolveInfo = new ResolveInfo(
            $operation->definition->namespace,
            $operation->definition->name,
            $operation->definition->type,
            $operation->definition->fullyQualifiedClassName,
            $operation->definition->methodName,
            [...$this->configuration->middleware, ...$operation->definition->middlewareClassNames()],
        );

        // Resolving happens before the pipeline exists, so it needs its own guard to keep
        // query()/command() total: a missing container binding or a class that is not a
        // middleware must surface as an RpcError, not as an uncaught exception. Nothing was
        // executing yet, so there is no scope whose declarations could apply.
        try {
            // Global middleware carries no config, so it skips the configuration path entirely.
            $middlewares = [
                ...array_map($this->adapter->createMiddleware(...), $this->configuration->middleware),
                ...array_map($this->createMiddleware(...), $operation->definition->middleware),
            ];
            $controllerClass = $this->adapter->createController($operation->definition->fullyQualifiedClassName);
        } catch (Throwable $throwable) {
            return $this->present($throwable, $resolveInfo);
        }

        return new ContextualPipeline(
            middlewares: $middlewares,
            onError: fn (Throwable $throwable, ?ExceptionScope $scope): RpcError => $this->present(
                $throwable,
                $resolveInfo,
                $scope,
            ),
            destination: function (mixed $input) use ($controllerClass, $client, $operation, $context, $resolveInfo): RpcSuccess|RpcError {
                $inputValidationResult = $this
                    ->executor
                    ->parse($operation->inputNode(), $input, new ParsingOptions(
                        coercePrimitives: $operation->definition->type === OperationType::QUERY
                            ? $this->configuration->coerceQueryInput
                            : false,
                    ));

                if ($inputValidationResult instanceof Failure) {
                    return $this->present(
                        new InvalidInputException($inputValidationResult),
                        $resolveInfo,
                    );
                }

                // Caught here, not by the pipeline: the pipeline only knows its middlewares, so
                // the handler's scope - the one whose #[Throws] declarations apply - is supplied
                // by the server itself.
                try {
                    /** @phpstan-ignore-next-line method.dynamicName */
                    $result = $controllerClass->{$operation->definition->methodName}($inputValidationResult->value, $context, $client);
                } catch (Throwable $throwable) {
                    return $this->present(
                        $throwable,
                        $resolveInfo,
                        new ExceptionScope($operation->definition->fullyQualifiedClassName, $operation->definition->methodName),
                    );
                }

                // partialFailures is off on purpose: it substitutes null wherever a value fails
                // to serialize under a null-accepting union, which would answer 200 with data
                // the operation never produced. An output that does not match its declared type
                // is a bug in the application, and the client is told so.
                $serializedResult = $this->executor
                    ->serialize(
                        $operation->outputNode(),
                        $result,
                        new SerializationOptions(partialFailures: false),
                    );

                if ($serializedResult instanceof Failure) {
                    // Deliberately not scope resolved: an output mismatch is a bug, never a
                    // declarable category.
                    return $this->present(
                        new InvalidOutputException($serializedResult),
                        $resolveInfo,
                    );
                }

                return new RpcSuccess($serializedResult->value, $client, $resolveInfo);
            },
        )->execute($input, $context, $resolveInfo, $client);
    }

    /**
     * Discovery already proved the declared class implements ConfigurableMiddleware when config
     * is present. The check here is about the instance, because the adapter owns instantiation
     * and may hand out a decorator or container substitute for that class-string.
     *
     * configure() runs on a private clone, so even a configure() that mutates $this can never
     * leak one operation's config into a container-shared instance.
     */
    private function createMiddleware(MiddlewareDefinition $definition): MiddlewareContract
    {
        $middleware = $this->adapter->createMiddleware($definition->middleware);

        if ($definition->config === []) {
            return $middleware;
        }

        if (! $middleware instanceof ConfigurableMiddleware) {
            throw InvalidMiddlewareException::notConfigurable($middleware::class);
        }

        return (clone $middleware)->configure($definition->config);
    }

    /**
     * The category comes from the throwing scope's own #[Throws]/#[ExposeAs] declarations first,
     * and from the configured classifier lists only where that scope declared nothing - the scope
     * that threw knows best what its own exception means. The details then restate what the
     * category alone cannot say: which fields failed validation, or which domain error this is.
     *
     * Presenting may itself throw (a stale class name failing reflection): that is a bug in the
     * setup, not a request-time condition, and it is allowed to escape.
     *
     * @param ExceptionScope|null $scope the scope the exception came from, or null when nothing
     *                                      was executing (unknown operation, resolution failure) or the failure is the server's own
     *                                      (input/output mismatch): only the classifier applies.
     *
     * @throws ReflectionException
     */
    private function present(
        Throwable       $throwable,
        ?ResolveInfo    $info,
        ?ExceptionScope $scope = null,
    ): RpcError {
        try {
            // If a scope is given, try to resolve the exception within its own declarations. A
            // globally configured middleware may not expose domain errors - it runs for every
            // operation, so a domain vocabulary there would leak into all of them.
            if ($scope) {
                $definedExceptions = ThrowAttributeResolver::resolveReflection(
                    $scope->toReflection(),
                    allowDomainErrors: !in_array($scope->className, $this->configuration->middleware, true),
                )['data'];

                foreach ($definedExceptions as $className => $presentConfig) {
                    if ($throwable instanceof $className) {
                        return new RpcError(
                            type: $presentConfig['type'],
                            cause: $throwable,
                            details: $this->detailsFor($presentConfig['type'], $throwable, $presentConfig['name'] ?? null),
                            resolveInfo: $info,
                        );
                    }
                }
            }

            $type = $this->classifier->classify($throwable);

            return new RpcError(
                type: $type,
                cause: $throwable,
                details: $this->detailsFor($type, $throwable),
                resolveInfo: $info,
            );
        } catch (Throwable $throwable) {
            return new RpcError(
                type: ErrorType::INTERNAL_ERROR,
                cause: $throwable,
                details: null,
                resolveInfo: $info,
            );
        }
    }

    /**
     * Shapes the details from the already resolved category - never from the exception class
     * alone. RATE_LIMITED always carries {retryIn: ?int}: the branch's shape must not depend on
     * whether a resolver is configured, only the value may.
     *
     * @return array<string, mixed>|null
     */
    private function detailsFor(ErrorType $type, Throwable $throwable, ?string $domainName = null): ?array
    {
        return match ($type) {
            ErrorType::DOMAIN_ERROR => ['name' => Assertions::string($domainName)],
            ErrorType::INVALID_INPUT => $throwable instanceof InvalidInputException
                ? ['fields' => $throwable->failure->issues->serializeToFieldsArray()]
                : null,
            ErrorType::RATE_LIMITED => ['retryIn' => $this->configuration->resolveRetryIn?->__invoke($throwable)],
            default => null,
        };
    }
}
