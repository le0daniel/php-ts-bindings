<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Unit\Parser\Data\Stubs\SomeFileInterface;

/**
 * An interface can be serialized but never built from an incoming payload, so this operation has no
 * honest TypeScript input type.
 */
final class UnrepresentableOperations
{
    /**
     * @return array{ok: bool}
     */
    #[Query('files')]
    public function get(SomeFileInterface $input): array
    {
        return ['ok' => $input->id > 0];
    }
}
