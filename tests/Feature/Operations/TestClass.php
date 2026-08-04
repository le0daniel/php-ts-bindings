<?php declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Data\PreviewableFileData;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;

final class TestClass
{
    /**
     * @param array{name: string} $data
     * @return array{message: string}
     */
    #[Command("test")]
    #[Middleware(NameCheckingMiddleware::class)]
    public function run(array $data): array
    {
        return [
            'message' => "Hello {$data['name']}",
        ];
    }

    /**
     * Returns something its own return type does not describe: `name` is an int where a string is
     * declared. The whole `user` branch is nullable, which is exactly the shape that used to be
     * answered as a 200 with `user: null`.
     *
     * @param array{ping: bool} $data
     * @return array{id: int, user: array{name: string}|null}
     */
    #[Command("test")]
    public function badOutput(array $data): array
    {
        /** @phpstan-ignore-next-line return.type (deliberately wrong, this is the fixture) */
        return ['id' => 1, 'user' => ['name' => 123]];
    }
}