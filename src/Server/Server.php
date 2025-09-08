<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\ExceptionPresenter;
use Le0daniel\PhpTsBindings\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\UnknownResultTypeException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\RpcError;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
use Le0daniel\PhpTsBindings\Server\Pipeline\ContextualPipeline;
use Le0daniel\PhpTsBindings\Server\Presenter\CatchAllPresenter;
use Psr\Container\ContainerInterface;
use Throwable;

final readonly class Server
{
    /**
     * @param OperationRegistry $registry
     * @param SchemaExecutor $executor
     * @param list<ExceptionPresenter> $exceptionPresenters
     * @param ExceptionPresenter $defaultPresenter
     */
    public function __construct(
        public OperationRegistry  $registry,
        public SchemaExecutor     $executor,
        public array              $exceptionPresenters,
        public ExceptionPresenter $defaultPresenter = new CatchAllPresenter(),
    )
    {
    }

    public function query(string $name, mixed $input, mixed $context, Client $client, ?ContainerInterface $container = null): RpcError|RpcSuccess
    {
        if (!$this->registry->has('query', $name)) {
            return new RpcError(
                ErrorType::NOT_FOUND,
                new OperationNotFoundException("Operation with name: {$name} was not found."),
                ['type' => 'NOT_FOUND']
            );
        }

        return $this->execute($this->registry->get('query', $name), $input, $context, $client, $container);
    }

    public function command(string $name, mixed $input, mixed $context, Client $client, ?ContainerInterface $container = null): RpcError|RpcSuccess
    {
        if (!$this->registry->has('command', $name)) {
            return new RpcError(
                ErrorType::NOT_FOUND,
                new OperationNotFoundException("Operation with name: {$name} was not found."),
                ['type' => 'NOT_FOUND']
            );
        }

        return $this->execute($this->registry->get('command', $name), $input, $context, $client, $container);
    }

    private function execute(Operation $operation, mixed $input, mixed $context, Client $client, ?ContainerInterface $container): RpcError|RpcSuccess
    {
        $validatedInput = $this->executor->parse($operation->inputNode(), $input);
        if ($validatedInput instanceof Failure) {
            return $this->produceError(
                new InvalidInputException($validatedInput),
                $operation->definition
            );
        }

        $middlewares = array_map(fn(string $className) => $container ? $container->get($className) : new $className, $operation->definition->middleware);
        $controllerClass = $container ? $container->get($operation->definition->fullyQualifiedClassName) : new $operation->definition->fullyQualifiedClassName;

        $resolveInfo = new ResolveInfo(
            $operation->definition->namespace,
            $operation->definition->name,
            $operation->definition->type,
            $operation->definition->fullyQualifiedClassName,
            $operation->definition->methodName,
        );

        $result = new ContextualPipeline($middlewares)
            ->catchErrorsWith(fn(Throwable $throwable) => $throwable)
            ->then(function (mixed $input) use ($controllerClass, $client, $operation, $context) {
                try {
                    $result = $controllerClass->{$operation->definition->methodName}($input, $context, $client);
                    return $this->executor->serialize($operation->outputNode(), $result);
                } catch (Throwable $exception) {
                    return $exception;
                }
            })
            ->execute($validatedInput->value, $context, $resolveInfo, $client);

        if ($result instanceof Success) {
            return new RpcSuccess($result->value, $client);
        }

        return $this->produceError(
            $result instanceof Throwable ? $result : UnknownResultTypeException::fromResult($result),
            $operation->definition
        );
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