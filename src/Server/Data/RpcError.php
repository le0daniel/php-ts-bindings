<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\RpcResult;
use NoDiscard;
use Override;
use Throwable;

final readonly class RpcError implements RpcResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ErrorType    $type,
        public Throwable    $cause,
        public mixed        $details,
        public ?ResolveInfo $resolveInfo,
        public array        $metadata = [],
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
        return new self($this->type, $this->cause, $this->details, $this->resolveInfo, $metadata);
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
        return new self($this->type, $this->cause, $this->details, $this->resolveInfo, [
            ...$this->metadata,
            ...$metadata,
        ]);
    }
}