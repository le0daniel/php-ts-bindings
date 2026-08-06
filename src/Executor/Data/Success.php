<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

final readonly class Success
{
    public function __construct(
        public mixed $value,
        public Issues $issues = new Issues(),
    ) {
    }

    public function isSuccess(): true
    {
        return true;
    }

    /**
     * A success that still collected issues: parsing ran with partialFailures enabled and kept
     * going past the parts that did not validate.
     */
    public function isPartial(): bool
    {
        return ! $this->issues->isEmpty();
    }
}
