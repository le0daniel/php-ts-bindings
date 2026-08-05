<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\RpcResult;
use Le0daniel\PhpTsBindings\Utils\Dicts;
use NoDiscard;
use Override;
use Throwable;

final readonly class RpcError implements RpcResult
{
    /**
     * @param Throwable $cause The exception the application threw. Always the original, so it can
     *   be handed straight to a reporter.
     * @param Throwable|null $presentationFailure Set only when working out how to present $cause
     *   itself failed - a stale #[Middleware] class name makes ExposedExceptions throw, for
     *   instance. When this is non null the category is INTERNAL_ERROR because the catalogue could
     *   not be consulted, not because $cause deserved a 500.
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ErrorType    $type,
        public Throwable    $cause,
        public mixed        $details,
        public ?ResolveInfo $resolveInfo,
        public array        $metadata = [],
        public ?Throwable   $presentationFailure = null,
    )
    {
    }

    /**
     * @param array<string, mixed> $metadata
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function withMetadata(array $metadata): self
    {
        return clone($this, [
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function appendMetadata(array $metadata): self
    {
        return clone($this, [
            'metadata' => [...$this->metadata, ...$metadata],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Dicts::filterNullValues([
            'success' => false,
            'code' => $this->type->value,
            // The discriminant the generated error union is narrowed on. The status code carries the
            // same information, but only the client that reads the body can rely on it.
            'type' => $this->type->name,
            'details' => $this->details,
            '__metadata' => count($this->metadata) > 0 ? $this->metadata : null,
        ]);
    }
}
