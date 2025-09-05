<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

use Throwable;

final readonly class RpcError
{
    public function __construct(
        public ErrorType $type,
        public Throwable $cause,
        public mixed     $details,
    )
    {
    }
}