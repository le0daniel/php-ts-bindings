<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Le0daniel\PhpTsBindings\Executor\Contracts\Result;
use Override;

final readonly class Success implements Result
{
    public function __construct(
        public mixed $value,
        public Issues $issues = new Issues(),
    ) {}

    #[Override]
    public function isSuccess(): true
    {
        return true;
    }

    #[Override]
    public function issues(): Issues
    {
        return $this->issues;
    }

    /**
     * A success that still collected issues: parsing ran with partialFailures enabled and kept
     * going past the parts that did not validate.
     */
    public function isPartial(): bool
    {
        return !$this->issues->isEmpty();
    }
}
