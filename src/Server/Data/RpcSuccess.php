<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\RpcResult;
use NoDiscard;
use Override;

final readonly class RpcSuccess implements RpcResult
{
    /**
     * @param array<string, mixed> $metadata
     * @internal
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
     * @return static
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function withMetadata(array $metadata): static
    {
        return new self($this->data, $this->client, $this->resolveInfo, $metadata);
    }

    /**
     * Append metadata to the result
     * @param array<string, mixed> $metadata
     * @return static
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function appendMetadata(array $metadata): static
    {
        return new self($this->data, $this->client, $this->resolveInfo, [
            ...$this->metadata,
            ...$metadata,
        ]);
    }
}