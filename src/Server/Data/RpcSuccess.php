<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\RpcResult;
use Le0daniel\PhpTsBindings\Contracts\SerializableClient;
use Le0daniel\PhpTsBindings\Utils\Dicts;
use NoDiscard;
use Override;

final readonly class RpcSuccess implements RpcResult
{
    public int $statusCode;

    /**
     * @param  array<string, mixed>  $metadata
     *
     * @internal
     */
    public function __construct(
        public mixed $data,
        public Client $client,
        public ResolveInfo $resolveInfo,
        public array $metadata = [],
    ) {
        $this->statusCode = 200;
    }

    /**
     * Overwrite all existing metadata
     *
     * @param  array<string, mixed>  $metadata
     *
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function withMetadata(array $metadata): self
    {
        return clone ($this, ['metadata' => $metadata]);
    }

    /**
     * Append metadata to the result
     *
     * @param  array<string, mixed>  $metadata
     *
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function appendMetadata(array $metadata): self
    {
        return clone ($this, [
            'metadata' => [...$this->metadata, ...$metadata],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $metadata = Dicts::filterNullValues([
            '__client' => $this->client instanceof SerializableClient ? $this->client->serializeToArray() : null,
            '__metadata' => count($this->metadata) > 0 ? $this->metadata : null,
        ]);

        return [
            ...$metadata,
            'success' => true,
            'data' => $this->data,
        ];
    }
}
