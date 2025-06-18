<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final readonly class Success
{
    public function __construct(
        public mixed $value,
        public Issues $issues = new Issues(),
    ) {}

    public function isPartial(): bool
    {
        return !$this->issues->isEmpty();
    }

}