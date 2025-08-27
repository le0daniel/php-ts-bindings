<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Exceptions;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;

final class InputValidationFailedException extends \Exception
{
    public function __construct(public readonly Failure $failure)
    {
        parent::__construct("Input validation failed", 422, $this->failure);
    }
}