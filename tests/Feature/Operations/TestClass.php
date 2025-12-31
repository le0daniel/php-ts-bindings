<?php declare(strict_types=1);

namespace Tests\Feature\Operations;

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
}