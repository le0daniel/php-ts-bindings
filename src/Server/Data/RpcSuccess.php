<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\Client;

final readonly class RpcSuccess
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public mixed       $data,
        public Client      $client,
        public ResolveInfo $resolveInfo,
        public array       $metadata = [],
    )
    {
    }

    /**
     * Overwrite all existing metadata
     * @param array<string, mixed> $metadata
     * @return self
     * @api
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->data, $this->client, $this->resolveInfo, $metadata);
    }

    /**
     * Append metadata to the result
     * @param array<string, mixed> $metadata
     * @return self
     * @api
     */
    public function appendMetadata(array $metadata): self
    {
        return new self($this->data, $this->client, $this->resolveInfo, [
            ...$this->metadata,
            ...$metadata,
        ]);
    }
}