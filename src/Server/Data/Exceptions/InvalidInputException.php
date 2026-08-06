<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;

/**
 * @internal This class in internal and should not be used outside of the library.
 * It strictly represents a failure to validate input data.
 *
 * The server constructs it from a parse Failure and ErrorPresenter is its only reader, turning it
 * into the 422 that carries `details.fields`. Never throw it: a 422 is the schema's verdict on the
 * input and nothing else. A rule the schema cannot express belongs in a value object throwing
 * ValidationException, or - when it needs context the input alone cannot give - in a domain error
 * declared with #[Throws]. A consumer only ever meets this class as RpcError::$cause.
 */
final class InvalidInputException extends SchemaException
{
    public function __construct(public readonly Failure $failure)
    {
        parent::__construct("Input validation failed", 422);
    }
}
