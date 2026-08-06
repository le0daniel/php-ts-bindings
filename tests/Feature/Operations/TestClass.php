<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Tests\Mocks\ValueObjects\ValidatedEmail;

final class TestClass
{
    /**
     * @param  array{name: string}  $data
     * @return array{message: string}
     */
    #[Command('test')]
    #[Middleware(NameCheckingMiddleware::class)]
    public function run(array $data): array
    {
        return [
            'message' => "Hello {$data['name']}",
        ];
    }

    /**
     * The value object rejects with a ValidationException, so the messages it names have to survive
     * all the way to details.fields rather than being flattened into validation.invalid_value.
     *
     * @param  array{email: ValidatedEmail}  $data
     * @return array{email: string}
     */
    #[Command('test')]
    public function acceptEmail(array $data): array
    {
        return ['email' => $data['email']->toStringValue()];
    }

    /**
     * Returns something its own return type does not describe: `name` is an int where a string is
     * declared. The whole `user` branch is nullable, which is exactly the shape that used to be
     * answered as a 200 with `user: null`.
     *
     * @param  array{ping: bool}  $data
     * @return array{id: int, user: array{name: string}|null}
     */
    #[Command('test')]
    public function badOutput(array $data): array
    {
        /** @phpstan-ignore-next-line return.type (deliberately wrong, this is the fixture) */
        return ['id' => 1, 'user' => ['name' => 123]];
    }
}
