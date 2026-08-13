<?php

declare(strict_types=1);

namespace Tests\Unit\Executor\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

/**
 * CONSTRUCTOR strategy with members hidden per direction: the promoted protected param is INPUT
 * only, the private(set) prop is OUTPUT only, and the write-only virtual prop is skipped entirely
 * because reading it would throw during serialization.
 */
#[Castable]
final class ApiCredentials
{
    public string $plainSecret {
        set {
            $this->obfuscated = str_repeat('*', strlen($value));
        }
    }

    public private(set) string $obfuscated = '';

    public function __construct(
        public readonly string $keyId,
        protected string $secret,
    ) {
    }
}
