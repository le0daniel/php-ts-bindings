<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Client;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use UnitEnum;

final class NullClient implements Client
{

    public function toast(string $type, string $message): void
    {

    }

    public function redirect(string $url): void
    {

    }

    public function hardRedirect(string $url): void
    {

    }

    public function invalidate(UnitEnum|string $namespace, ...$key): void
    {

    }
}