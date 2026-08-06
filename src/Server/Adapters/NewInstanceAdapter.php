<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Adapters;

use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;
use Le0daniel\PhpTsBindings\Contracts\ServerAdapter;

final readonly class NewInstanceAdapter implements ServerAdapter
{
    public function createMiddleware(string $className): MiddlewareContract
    {
        return new $className();
    }

    public function createController(string $className): object
    {
        return new $className();
    }
}
