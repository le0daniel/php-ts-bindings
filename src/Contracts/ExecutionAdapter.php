<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Operations\Data\OperationDefinition;
use Throwable;

/**
 * @template REQUEST
 * @template RESPONSE
 * @template CONTEXT
 */
interface ExecutionAdapter
{
    /**
     * Create the context needed for the execution. This can be by bundling more data,
     * or you can also return the request itself.
     *
     * @param REQUEST $request
     * @return CONTEXT
     */
    public function createContext(mixed $request): mixed;

    /**
     * When execution starts, after the endpoint was located,
     * this method is invoked. It should return the input data that will later be used
     * for the endpoint. An HTTP request adapter could, for example, expect the input to be of type JSON.
     *
     * This method is not tasked with identifying if the input is valid. This is handled by the Bindings Manager.
     *
     * @param 'query'|'command' $type
     * @param REQUEST $request
     * @return mixed
     */
    public function getInputFromRequest(string $type, mixed $request): mixed;

    /**
     * Should invoke a given endpoint. It gets the parsed and validated typed input data for
     * the Endpoint already. It should return its raw response.
     *
     * This is the place where the class should be created, dependencies correctly injected.
     * The full
     *
     * @param OperationDefinition $definition
     * @param mixed $input
     * @param CONTEXT $context
     * @return mixed
     */
    public function invokeEndpoint(OperationDefinition $definition, mixed $input, mixed $context): mixed;

    /**
     * @param Failure $failure
     * @param CONTEXT $context
     * @return RESPONSE
     */
    public function produceInvalidInputResponse(Failure $failure, mixed $context): mixed;

    /**
     * @param 'query'|'command' $type
     * @param string $fullyQualifiedOperationName
     * @param REQUEST $request
     * @return RESPONSE
     */
    public function produceOperationNotFoundResponse(string $type, string $fullyQualifiedOperationName, mixed $request): mixed;

    /**
     * Produce a response from an error that occurred. Those are unexpected errors that where thrown
     * @param Throwable $throwable
     * @param CONTEXT $context
     * @return RESPONSE
     */
    public function produceInternalErrorResponse(Throwable $throwable, mixed $context): mixed;

    /**
     * Given a handled exception, produce the correct response for the client.
     *
     * @param ClientAwareException $exception
     * @param CONTEXT $context
     * @return RESPONSE
     */
    public function produceClientAwareErrorResponse(ClientAwareException $exception, mixed $context): mixed;

    /**
     * Gets the serialized Result as success or failure. The adapter should handle the crafting of
     * a valid response that can be returned by the invoker.
     *
     * @param Success|Failure $result
     * @param CONTEXT $context
     * @return mixed
     */
    public function produceResponse(Success|Failure $result, mixed $context): mixed;
}