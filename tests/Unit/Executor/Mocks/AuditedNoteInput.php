<?php

declare(strict_types=1);

namespace Tests\Unit\Executor\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

/**
 * A zero-argument constructor resolves to ASSIGN_PROPERTIES, not CONSTRUCTOR, so the readonly
 * property stays server-controlled: initialized when the executor runs `new AuditedNoteInput()`
 * and exposed as OUTPUT only.
 */
#[Castable]
final class AuditedNoteInput
{
    public string $note;

    public readonly string $recordedBy;

    public function __construct()
    {
        $this->recordedBy = 'system';
    }
}
