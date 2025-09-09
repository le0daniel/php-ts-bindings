<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Exception;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;

final class InvalidOutputException extends Exception
{
    public Issues $issues {
        get => $this->failure->issues;
    }

    public function __construct(private readonly Failure $failure)
    {
        parent::__construct($failure->message, 500, $failure);
    }
}