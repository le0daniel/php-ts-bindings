<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * Property hooks and asymmetric visibility, each property landing in a different direction:
 * code is plain (BOTH), summary is a virtual getter (OUTPUT only), checksum is private(set)
 * (OUTPUT only), and raw is a virtual setter (INPUT only) whose hook fills checksum.
 */
#[Castable(ObjectCastStrategy::ASSIGN_PROPERTIES)]
final class HandlingInstructions
{
    public string $code;

    public private(set) string $checksum = '';

    public string $raw {
        set {
            $this->checksum = strrev($value);
        }
    }

    public string $summary {
        get => strtoupper($this->code);
    }
}
