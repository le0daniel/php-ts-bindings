<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\KeyGenerators;

use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Override;

final readonly class PlainlyExposedKeyGenerator implements OperationKeyGenerator
{
    #[Override]
    public function generateKey(string $namespace, string $name): string
    {
        return "{$namespace}.{$name}";
    }
}
