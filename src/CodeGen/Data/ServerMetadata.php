<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Data;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;

final readonly class ServerMetadata
{
    public function __construct(
        public string $queryUrl,
        public string $commandUrl,
    )
    {
        if (!str_contains($this->queryUrl, '{fqn}')) {
            throw new InvalidArgumentException('Query URL must contain {fqn} placeholder');
        }
        if (!str_contains($this->commandUrl, '{fqn}')) {
            throw new InvalidArgumentException('Command URL must contain {fqn} placeholder');
        }
    }

    public function getFullyQualifiedUrl(Operation $operation): string
    {
        return $operation->definition->type === OperationType::QUERY
            ? str_replace('{fqn}', $operation->key, $this->queryUrl)
            : str_replace('{fqn}', $operation->key, $this->commandUrl);
    }
}