<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final readonly class Success
{
    public function __construct(
        public mixed $value,
    ) {}
}