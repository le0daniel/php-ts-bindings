<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Client;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Server\Data\Toast;
use Override;
use UnitEnum;

final readonly class NullClient implements Client
{
    use InteractsWithToasts;

    #[Override]
    public function toast(Toast $toast): void
    {

    }

    #[Override]
    public function redirect(string $url, bool $reload = false): void
    {

    }

    #[Override]
    public function invalidate(UnitEnum|string $namespace, ...$key): void
    {

    }
}
