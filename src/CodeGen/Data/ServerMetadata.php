<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Data;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;

final readonly class ServerMetadata
{
    public function __construct(
        public string $queryUrl,
        public string $commandUrl,
    ) {
        if (! str_contains($this->queryUrl, '{fqn}')) {
            throw new CodeGenException('Query URL must contain {fqn} placeholder');
        }
        if (! str_contains($this->commandUrl, '{fqn}')) {
            throw new CodeGenException('Command URL must contain {fqn} placeholder');
        }
    }

}
