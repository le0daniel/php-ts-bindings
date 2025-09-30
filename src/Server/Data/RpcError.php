<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Throwable;

final readonly class RpcError
{
    /**
     * @param ErrorType $type
     * @param Throwable $cause
     * @param mixed $details
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ErrorType $type,
        public Throwable $cause,
        public mixed     $details,
        public array     $metadata = [],
    )
    {
    }

    /**
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->type, $this->cause, $this->details, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function appendMetadata(array $metadata): self
    {
        return new self($this->type, $this->cause, $this->details, [
            ...$this->metadata,
            ...$metadata,
        ]);
    }
}