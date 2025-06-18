<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final class Failure
{
    public function __construct(
        public Issues $issues,
    )
    {
    }
}