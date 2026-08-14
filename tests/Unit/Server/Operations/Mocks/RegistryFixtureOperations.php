<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Operations\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;

final class RegistryFixtureOperations
{
    /**
     * @param  array{name: string}  $input
     * @return array{greeting: string}
     */
    #[Query('registry')]
    public function greet(array $input): array
    {
        return ['greeting' => "Hello {$input['name']}"];
    }

    /**
     * @param  array{name: string}  $input
     * @return array{name: string}
     */
    #[Command('registry')]
    public function rename(array $input): array
    {
        return $input;
    }
}
