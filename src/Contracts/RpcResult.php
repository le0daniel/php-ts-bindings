<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

/**
 * The two possible outcomes of an operation: RpcSuccess and RpcError.
 *
 * Signatures keep spelling out the `RpcSuccess|RpcError` union so the narrow type survives; this
 * interface exists so that middleware can decorate a result it has not inspected yet.
 */
interface RpcResult
{
    /**
     * Overwrite all existing metadata.
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): static;

    /**
     * Append metadata to the result.
     * @param array<string, mixed> $metadata
     */
    public function appendMetadata(array $metadata): static;
}
