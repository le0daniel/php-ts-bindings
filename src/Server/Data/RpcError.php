<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\RpcResult;
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
     * @return static
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function withMetadata(array $metadata): static
    {
        return new self(
            $this->type,
            $this->cause,
            $this->details,
            $this->resolveInfo,
            $metadata,
            $this->presentationFailure,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @return static
     * @api
     */
    #[Override]
    #[NoDiscard]
    public function appendMetadata(array $metadata): static
    {
        return new self(
            $this->type,
            $this->cause,
            $this->details,
            $this->resolveInfo,
            [...$this->metadata, ...$metadata],
            $this->presentationFailure,
        );
    }
}
