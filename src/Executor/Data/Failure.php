<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

use Le0daniel\PhpTsBindings\Executor\Contracts\Result;
use Override;

/**
 * A value did not validate. Returned from the executor, never thrown - a value the caller supplied
 * being wrong is an outcome, not an exceptional condition.
 *
 * Deliberately not a Throwable: while it was one, a consumer with a broad catch around executor
 * code could swallow a Failure that had leaked out of a return value. Where the failure does need
 * to travel as an exception - across the RPC boundary - InvalidInputException and
 * InvalidOutputException wrap it.
 */
final readonly class Failure implements Result
{
    public function __construct(
        public Issues $issues,
    )
    {
    }

    #[Override]
    public function isSuccess(): false
    {
        return false;
    }

    #[Override]
    public function issues(): Issues
    {
        return $this->issues;
    }

    /**
     * The message the wrapping exceptions report, kept here so both of them describe a failure the
     * same way.
     */
    public function describe(): string
    {
        return "Validation failed: {$this->issues->serializeToCompleteString()}.";
    }
}
