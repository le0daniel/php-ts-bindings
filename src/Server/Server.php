<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\ParsingOptions;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
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
use Psr\Container\ContainerInterface;
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
                ['type' => 'NOT_FOUND']
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
                ['type' => 'NOT_FOUND']
            );
        }

        return $this->execute($this->registry->get(OperationType::COMMAND, $name), $input, $context, $client);
    }

    private function execute(Operation $operation, mixed $input, mixed $context, Client $client): RpcError|RpcSuccess
    {
        $validatedInput = $this->executor->parse($operation->inputNode(), $input, new ParsingOptions(
            coercePrimitives: $operation->definition->type === OperationType::QUERY
                ? $this->configuration->coerceQueryInput
                : false,
        ));

        if ($validatedInput instanceof Failure) {
            return $this->produceError(
                new InvalidInputException($validatedInput),
                $operation->definition
            );
        }

        $middlewareClassNames = [
            ... $this->configuration->middleware,
            ... $operation->definition->middleware,
        ];
        $middlewares = array_map(
            fn(string $className) => $this->container
                ? $this->container->get($className)
                : new $className,
            $middlewareClassNames
        );

        $controllerClass = $this->container
            ? $this->container->get($operation->definition->fullyQualifiedClassName)
            : new $operation->definition->fullyQualifiedClassName;

        $resolveInfo = new ResolveInfo(
            $operation->definition->namespace,
            $operation->definition->name,
            $operation->definition->type,
            $operation->definition->fullyQualifiedClassName,
            $operation->definition->methodName,
            $middlewareClassNames,
        );

        return new ContextualPipeline($middlewares)
            ->catchErrorsWith(fn(Throwable $throwable) => new RpcError(ErrorType::INTERNAL_ERROR, $throwable, []))
            ->then(function (mixed $input) use ($controllerClass, $client, $operation, $context): RpcSuccess|RpcError {
                try {
                    $serializedResult = $this->executor
                        ->serialize(
                            $operation->outputNode(),
                            $controllerClass->{$operation->definition->methodName}($input, $context, $client)
                        );

                    if ($serializedResult instanceof Failure) {
                        return $this->produceError(
                            new InvalidOutputException($serializedResult),
                            $operation->definition
                        );
                    }

                    return new RpcSuccess($serializedResult->value, $client);
                } catch (Throwable $exception) {
                    return new RpcError(ErrorType::INTERNAL_ERROR, $exception, []);
                }
            })
            ->execute($validatedInput->value, $context, $resolveInfo, $client);
    }

    /**
     * @param Throwable $exception
     * @param Definition $definition
     * @return RpcError
     */
    private function produceError(Throwable $exception, Definition $definition): RpcError
    {
        foreach ($this->exceptionPresenters as $presenter) {
            if ($presenter->matches($exception, $definition)) {
                return new RpcError(
                    $presenter::errorType(),
                    $exception,
                    $presenter->details($exception)
                );
            }
        }

        return new RpcError(
            $this->defaultPresenter::errorType(),
            $exception,
            $this->defaultPresenter->details($exception)
        );
    }
}