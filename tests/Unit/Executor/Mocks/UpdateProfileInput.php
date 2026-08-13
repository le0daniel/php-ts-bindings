<?php

declare(strict_types=1);

namespace Tests\Unit\Executor\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

/**
 * No constructor, so the ASSIGN_PROPERTIES strategy applies. Each property lands in a different
 * direction: plain props are BOTH, the virtual get-only and private(set) props are OUTPUT, the
 * virtual set-only prop is INPUT, and the backed set hook stays BOTH.
 */
#[Castable]
final class UpdateProfileInput
{
    public string $firstName;

    public string $lastName;

    public string $fullName {
        get => "{$this->firstName} {$this->lastName}";
    }

    public private(set) string $passwordHash = '';

    public string $password {
        set {
            $this->passwordHash = strrev($value);
        }
    }

    public string $displayName {
        set => trim($value);
    }
}
