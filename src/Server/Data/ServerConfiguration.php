<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

final readonly class ServerConfiguration
{
    public function __construct(
        public bool $coerceQueryInput = false,
    )
    {
    }
}