<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;

/**
 * @internal
 */
final class InvalidOutputException extends SchemaException
{
    public Issues $issues {
        get => $this->failure->issues;
    }

    public function __construct(private readonly Failure $failure)
    {
        parent::__construct($failure->describe(), 500);
    }
}
