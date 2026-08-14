<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;

final class ConfiguredGreeting
{
    /**
     * @param  array{name: string}  $input
     * @return array{message: string}
     */
    #[Command('configured')]
    #[Middleware(PrefixNameMiddleware::class, config: ['prefix' => 'Dr. '])]
    public function greet(array $input): array
    {
        return ['message' => "Hello {$input['name']}"];
    }

    /**
     * The same middleware without config runs with its constructor defaults.
     *
     * @param  array{name: string}  $input
     * @return array{message: string}
     */
    #[Command('configured')]
    #[Middleware(PrefixNameMiddleware::class)]
    public function greetPlain(array $input): array
    {
        return ['message' => "Hello {$input['name']}"];
    }
}
