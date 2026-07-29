<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Client;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Server\Data\Toast;
use UnitEnum;

final class NullClient implements Client
{
    use InteractsWithToasts;

    public function toast(Toast $toast): void
    {

    }

    public function redirect(string $url, bool $reload = false): void
    {

    }

    public function invalidate(UnitEnum|string $namespace, ...$key): void
    {

    }
}
