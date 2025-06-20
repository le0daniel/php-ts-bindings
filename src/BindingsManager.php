<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings;

use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Contracts\ExecutionAdapter;
use Le0daniel\PhpTsBindings\Executor\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Throwable;

/**
 * @template REQUEST
 * @template RESPONSE
 * @template CONTEXT
 */
final readonly class BindingsManager
{
    /**
     * @param ExecutionAdapter<REQUEST,RESPONSE,CONTEXT> $adapter
     * @param OperationRegistry $operations
     * @param SchemaExecutor $executor
     */
    public function __construct(
        private ExecutionAdapter $adapter,
        public OperationRegistry $operations,
        private SchemaExecutor $executor = new SchemaExecutor(),
    )
    {

    }

    /**
     * @param REQUEST $input
     * @return RESPONSE
     */
    public function executeQuery(string $fullyQualifiedName, mixed $input): mixed
    {
        return $this->execute('query', $fullyQualifiedName, $input);
    }

    /**
     * @param REQUEST $input
     * @return RESPONSE
     */
    public function executeCommand(string $fullyQualifiedName, mixed $input): mixed
    {
        return $this->execute('command', $fullyQualifiedName, $input);
    }

    /**
     * @param 'command'|'query' $type
     * @param string $fullyQualifiedName
     * @param REQUEST $request
     * @return RESPONSE
     */
    private function execute(string $type, string $fullyQualifiedName, mixed $request): mixed
    {
        if (!$this->operations->has($type, $fullyQualifiedName)) {
            return $this->adapter->produceOperationNotFoundResponse($type, $fullyQualifiedName, $request);
        }

        $endpoint = $this->operations->get($type, $fullyQualifiedName);
        $context = $this->adapter->createContext($request);

        $parsedInput = $this->executor->parse(
            $endpoint->input,
            $this->adapter->getInputFromRequest($type, $request),
        );

        if ($parsedInput instanceof Failure) {
            return $this->adapter->produceInvalidInputResponse($parsedInput, $context);
        }

        try {
            $result = $this->executor->parse(
                $endpoint->output,
                $this->adapter->invokeEndpoint($endpoint->definition, $parsedInput->value, $context),
            );

            return $this->adapter->produceResponse($result, $context);
        } catch (Throwable $exception) {
            if ($endpoint->isHandledException($exception)) {
                /** @var ClientAwareException $exception */
                return $this->adapter->produceClientAwareErrorResponse($exception, $context);
            }

            return $this->adapter->produceInternalErrorResponse($exception, $context);
        }
    }
}