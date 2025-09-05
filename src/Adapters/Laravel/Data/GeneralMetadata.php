<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Data;

use Le0daniel\PhpTsBindings\Server\Data\Operation;

final class GeneralMetadata
{
    public function __construct(
        public readonly string $queryUrl,
        public readonly string $commandUrl,
    )
    {
    }

    public function getFullyQualifiedUrl(Operation $operation): string
    {
        return $operation->definition->type === 'query'
            ? str_replace('{fqn}', $operation->key, $this->queryUrl)
            : str_replace('{fqn}', $operation->key, $this->commandUrl);
    }
}