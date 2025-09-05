<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Le0daniel\PhpTsBindings\Contracts\Client;

final readonly class RpcSuccess
{
    public function __construct(
        public mixed $data,
        public Client $client,
    )
    {
    }
}