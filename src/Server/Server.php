<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidMiddlewareException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidOutputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Pipeline\ContextualPipeline;
use Le0daniel\PhpTsBindings\Server\Presenter\CatchAllPresenter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

final readonly class Server
{
    public SchemaExecutor $executor;

    /**
     * @param OperationRegistry $registry
     * @param list<ExceptionPresenter> $exceptionPresenters
     * @param ExceptionPresenter $defaultPresenter
     * @param ContainerInterface|null $container
     * @param ServerConfiguration $configuration
     */
    public function __construct(
        public OperationRegistry        $registry,
        public array                    $exceptionPresenters,
        public ExceptionPresenter       $defaultPresenter = new CatchAllPresenter(),
        private null|ContainerInterface $container = null,
        public ServerConfiguration      $configuration = new ServerConfiguration(),
    )
    {
        $this->executor = new SchemaExecutor();
    }

    public function query(string $name, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        if (!$this->registry->has(OperationType::QUERY, $name)) {
            return new RpcError(
                ErrorType::NOT_FOUND,
                new OperationNotFoundException("Operation with name: {$name} was not found."),
                ['type' => 'NOT_FOUND'],
                null,
            );
        }

        return $this->execute($this->registry->get(OperationType::QUERY, $name), $input, $context, $client);
    }

    public function command(string $name, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        if (!$this->registry->has(OperationType::COMMAND, $name)) {
            return new RpcError(
                ErrorType::NOT_FOUND,
                new OperationNotFoundException("Operation with name: {$name} was not found."),
                ['type' => 'NOT_FOUND'],
                null,
            );
        }

        return $this->execute($this->registry->get(OperationType::COMMAND, $name), $input, $context, $client);
    }

    private function execute(Operation $operation, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        $middlewareClassNames = [
            ... $this->configuration->middleware,
            ... $operation->definition->middleware,
        ];

        $resolveInfo = new ResolveInfo(
            $operation->definition->namespace,
            $operation->definition->name,
            $operation->definition->type,
            $operation->definition->fullyQualifiedClassName,
            $operation->definition->methodName,
            $middlewareClassNames,
        );

        // Resolving happens before the pipeline exists, so it needs its own guard to keep
        // query()/command() total: a missing container binding or a class that is not a
        // middleware must surface as an RpcError, not as an uncaught exception.
        try {
            $middlewares = array_map($this->resolveMiddleware(...), $middlewareClassNames);
            $controllerClass = $this->container
                ? $this->container->get($operation->definition->fullyQualifiedClassName)
                : new $operation->definition->fullyQualifiedClassName;
        } catch (Throwable $throwable) {
            return $this->produceError($throwable, $operation->definition, $resolveInfo);
        }

        return new ContextualPipeline(
            middlewares: $middlewares,
            onError: fn(Throwable $throwable): RpcError => $this->produceError($throwable, $operation->definition, $resolveInfo),
            destination: function (mixed $input) use ($controllerClass, $client, $operation, $context, $resolveInfo): RpcSuccess|RpcError {
                try {
                    $inputValidationResult = $this
                        ->executor
                        ->parse($operation->inputNode(), $input, new ParsingOptions(
                            coercePrimitives: $operation->definition->type === OperationType::QUERY
                                ? $this->configuration->coerceQueryInput
                                : false,
                        ));

                    if ($inputValidationResult instanceof Failure) {
                        return $this->produceError(
                            new InvalidInputException($inputValidationResult),
                            $operation->definition,
                            $resolveInfo,
                        );
                    }

                    $serializedResult = $this->executor
                        ->serialize(
                            $operation->outputNode(),
                            $controllerClass->{$operation->definition->methodName}($inputValidationResult->value, $context, $client)
                        );

                    if ($serializedResult instanceof Failure) {
                        return $this->produceError(
                            new InvalidOutputException($serializedResult),
                            $operation->definition,
                            $resolveInfo,
                        );
                    }

                    return new RpcSuccess($serializedResult->value, $client, $resolveInfo);
                } catch (Throwable $throwable) {
                    return $this->produceError($throwable, $operation->definition, $resolveInfo);
                }
            },
        )->execute($input, $context, $resolveInfo, $client);
    }

    /**
     * @param class-string<MiddlewareContract<mixed>> $className
     * @return MiddlewareContract<mixed>
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    private function resolveMiddleware(string $className): MiddlewareContract
    {
        $middleware = $this->container
            ? $this->container->get($className)
            : new $className;

        if (!$middleware instanceof MiddlewareContract) {
            throw InvalidMiddlewareException::notAMiddleware($className);
        }

        return $middleware;
    }

    /**
     * @param Throwable $exception
     * @param Definition $definition
     * @return RpcError
     */
    private function produceError(Throwable $exception, Definition $definition, ?ResolveInfo $info): RpcError
    {
        foreach ($this->exceptionPresenters as $presenter) {
            if ($presenter->matches($exception, $definition)) {
                return new RpcError(
                    $presenter::errorType(),
                    $exception,
                    $presenter->details($exception),
                    $info
                );
            }
        }

        return new RpcError(
            $this->defaultPresenter::errorType(),
            $exception,
            $this->defaultPresenter->details($exception),
            $info
        );
    }
}