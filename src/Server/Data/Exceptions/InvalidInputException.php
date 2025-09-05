<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;

final class InvalidInputException extends \Exception
{
    public function __construct(public readonly Failure $failure)
    {
        parent::__construct("Input validation failed", 422, $this->failure);
    }
}