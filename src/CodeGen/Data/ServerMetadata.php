<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Data;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use NoDiscard;

final readonly class ServerMetadata
{
    /**
     * @param  ServerConfiguration  $configuration  Which categories the generated Failure is a union
     *                                              of depends on how this server maps exceptions, so
     *                                              the generators that emit it need to see it. The
     *                                              run fills it in from the Server; the default is
     *                                              the unconfigured catalogue, which is what a
     *                                              generator invoked on its own would produce anyway.
     */
    public function __construct(
        public string $queryUrl,
        public string $commandUrl,
        public ServerConfiguration $configuration,
    ) {
        if (! str_contains($this->queryUrl, '{fqn}')) {
            throw new CodeGenException('Query URL must contain {fqn} placeholder');
        }
        if (! str_contains($this->commandUrl, '{fqn}')) {
            throw new CodeGenException('Command URL must contain {fqn} placeholder');
        }
    }

    #[NoDiscard]
    public function withConfiguration(ServerConfiguration $configuration): self
    {
        return new self($this->queryUrl, $this->commandUrl, $configuration);
    }
}
