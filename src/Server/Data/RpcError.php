<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\RpcResult;
use Le0daniel\PhpTsBindings\Contracts\SerializableClient;
use Le0daniel\PhpTsBindings\Utils\Dicts;
use NoDiscard;
use Override;
use Throwable;

final class RpcError implements RpcResult
{
    public int $statusCode {
        get => $this->type->value;
    }

    /**
     * @param Throwable $cause The most recent failure - the one that decided this result. On an
     *   ordinary error that is simply the exception the application threw.
     * @param list<Throwable> $previous Everything that failed before $cause, oldest first. Empty on
     *   every ordinary error, and non empty only when handling one failure produced another: a
     *   stale #[Middleware] class name makes ExposedExceptions throw while categorising, and the
     *   result is then an INTERNAL_ERROR because the catalogue could not be consulted, not because
     *   the original deserved a 500. Reporters want all of them.
     * @param array<string, mixed> $metadata
     * @internal Constructed by the server. Applications receive one, they do not build one.
     */
    public function __construct(
        public readonly ErrorType    $type,
        public readonly Throwable    $cause,
        public readonly mixed        $details,
        public readonly ?ResolveInfo $resolveInfo,
        public readonly array        $metadata = [],
        public readonly array        $previous = [],
    )
    {
    }

    /**
     * Every failure that led here, oldest first, with $cause last.
     *
     * @return non-empty-list<Throwable>
     * @api
     */
    public function throwableChain(): array
    {
        return [...$this->previous, $this->cause];
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
    #[Override]
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
