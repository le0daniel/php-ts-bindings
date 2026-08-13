<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;

final class OperationNotFoundException extends SchemaException
{
    public static function forKey(string $key): self
    {
        return new self(
            "Unknown operation key '{$key}'. The operations cache is stale or was written by a "
            .'different build. Regenerate the operations cache.',
        );
    }
}
