<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Throwable;

final readonly class RpcError
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
     * @return self
     * @api
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->type, $this->cause, $this->details, $this->resolveInfo, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return self
     * @api
     */
    public function appendMetadata(array $metadata): self
    {
        return new self($this->type, $this->cause, $this->details, $this->resolveInfo, [
            ...$this->metadata,
            ...$metadata,
        ]);
    }
}