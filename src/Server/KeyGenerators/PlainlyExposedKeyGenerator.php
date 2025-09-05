<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\KeyGenerators;

use Le0daniel\PhpTsBindings\Contracts\OperationKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Data\Definition;

final class PlainlyExposedKeyGenerator implements OperationKeyGenerator
{

    public function generateKey(Definition $definition): string
    {
        return $definition->fullyQualifiedName();
    }
}