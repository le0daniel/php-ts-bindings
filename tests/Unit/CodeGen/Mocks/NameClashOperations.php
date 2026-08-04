<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;

/**
 * A query and a command may share a namespace.name - the registry keys them by type - but both
 * land in `clash.ts`, and under the default naming rule both emit `export async function get`.
 */
final class NameClashOperations
{
    /**
     * @param array{id: int} $input
     * @return array{id: int}
     */
    #[Query('clash', 'get')]
    public function read(array $input): array
    {
        return $input;
    }

    /**
     * @param array{id: int} $input
     * @return array{id: int}
     */
    #[Command('clash', 'get')]
    public function write(array $input): array
    {
        return $input;
    }
}
