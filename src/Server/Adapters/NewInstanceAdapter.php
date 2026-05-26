<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Adapters;

use Le0daniel\PhpTsBindings\Contracts\ServerExecutionAdapter;

final readonly class NewInstanceAdapter implements ServerExecutionAdapter
{

    public function createMiddleware(string $className): object
    {
        return new $className();
    }

    public function createOperationClass(string $className): object
    {
        return new $className();
    }
}