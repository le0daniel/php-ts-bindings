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

    /**
     * @param array{dueDate: DateTimeString<"Y-m-d">} $data
     * @return array{message: string, date: DateTimeString<"d.m.Y">}
     */
    #[Command("test")]
    public function someDateStuff(array $data): array
    {
        return [
            'message' => "Date Is {$data['dueDate']->format("d.m.Y")}",
            'date' => $data['dueDate']
        ];
    }
}