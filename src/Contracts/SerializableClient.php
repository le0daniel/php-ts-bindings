<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

/**
 * A Client whose collected directives are meant to travel back to the caller.
 *
 * Transports check for this interface to decide whether a client has anything to append to the
 * response at all. A client that only reacts locally (see NullClient) simply does not implement it.
 */
interface SerializableClient extends Client
{
    /**
     * Plain data only, ready to be encoded as-is. Returns null when no directive was collected,
     * so the transport can leave the key off the response entirely.
     *
     * @return array<string, mixed>|null
     */
    public function serializeToArray(): ?array;
}
