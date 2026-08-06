<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Contracts\ServerAdapter;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\Data\SerializationOptions;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Server\Adapters\NewInstanceAdapter;
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
use Le0daniel\PhpTsBindings\Server\Errors\ErrorPresenter;
use Le0daniel\PhpTsBindings\Server\Pipeline\ContextualPipeline;
use Throwable;

final readonly class Server
{
    public SchemaExecutor $executor;

    /**
     * Error presentation is not an extension point: the catalogue is finite and the server needs it
     * to run. What an application configures is which of its exceptions belong in which category.
     *
     * @see ErrorPresenter
     */
    private ErrorPresenter $errorPresenter;

    public function __construct(
        public OperationRegistry   $registry,
        private ServerAdapter      $adapter = new NewInstanceAdapter(),
        public ServerConfiguration $configuration = new ServerConfiguration(),
    )
    {
        $this->executor = new SchemaExecutor();
        $this->errorPresenter = new ErrorPresenter($configuration);
    }

    public function query(string $name, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        if (!$this->registry->has(OperationType::QUERY, $name)) {
            return $this->errorPresenter->present(
                new OperationNotFoundException("Operation with name: {$name} was not found."),
                null,
                null,
            );
        }

        return $this->execute($this->registry->get(OperationType::QUERY, $name), $input, $context, $client);
    }

    public function command(string $name, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        if (!$this->registry->has(OperationType::COMMAND, $name)) {
            return $this->errorPresenter->present(
                new OperationNotFoundException("Operation with name: {$name} was not found."),
                null,
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
            $middlewares = array_map(fn($className) => $this->adapter->createMiddleware($className), $middlewareClassNames);
            $controllerClass = $this->adapter->createController($operation->definition->fullyQualifiedClassName);
        } catch (Throwable $throwable) {
            return $this->errorPresenter->present($throwable, $operation->definition, $resolveInfo);
        }

        return new ContextualPipeline(
            middlewares: $middlewares,
            onError: fn(Throwable $throwable): RpcError => $this->errorPresenter->present($throwable, $operation->definition, $resolveInfo),
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
                        return $this->errorPresenter->present(
                            new InvalidInputException($inputValidationResult),
                            $operation->definition,
                            $resolveInfo,
                        );
                    }

                    // partialFailures is off on purpose: it substitutes null wherever a value fails
                    // to serialize under a null-accepting union, which would answer 200 with data
                    // the operation never produced. An output that does not match its declared type
                    // is a bug in the application, and the client is told so.
                    $serializedResult = $this->executor
                        ->serialize(
                            $operation->outputNode(),
                            /** @phpstan-ignore-next-line method.dynamicName */
                            $controllerClass->{$operation->definition->methodName}($inputValidationResult->value, $context, $client),
                            new SerializationOptions(partialFailures: false),
                        );

                    if ($serializedResult instanceof Failure) {
                        return $this->errorPresenter->present(
                            new InvalidOutputException($serializedResult),
                            $operation->definition,
                            $resolveInfo,
                        );
                    }

                    return new RpcSuccess($serializedResult->value, $client, $resolveInfo);
                } catch (Throwable $throwable) {
                    return $this->errorPresenter->present($throwable, $operation->definition, $resolveInfo);
                }
            },
        )->execute($input, $context, $resolveInfo, $client);
    }
}