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
     * The ids run 0, 1, 2, which is precisely when json_encode would render a PHP array as a JSON
     * array. `byId` is declared as a record, so it has to answer as an object regardless - the
     * client's `Record<string, ...>` is either always true or it is worthless.
     *
     * @param  array{ping: bool}  $data
     * @return array{byId: array<int, array{name: string}>, tags: list<string>, empty: array<string, int>}
     */
    #[Command('test')]
    public function packedRecord(array $data): array
    {
        return [
            'byId' => [
                0 => ['name' => 'zero'],
                1 => ['name' => 'one'],
                2 => ['name' => 'two'],
            ],
            'tags' => ['a', 'b'],
            'empty' => [],
        ];
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
