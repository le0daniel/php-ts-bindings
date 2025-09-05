<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\Client;
use Throwable;

final readonly class RpcError
{
    public function __construct(
        public int       $code,
        public Throwable $cause,
        public mixed     $details,
    )
    {
    }
}