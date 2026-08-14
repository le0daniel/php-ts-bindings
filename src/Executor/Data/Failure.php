<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

/**
 * A value did not validate. Returned from the executor, never thrown - a value the caller supplied
 * being wrong is an outcome, not an exceptional condition.
 *
 * Deliberately not a Throwable: while it was one, a consumer with a broad catch around executor
 * code could swallow a Failure that had leaked out of a return value. Where the failure does need
 * to travel as an exception - across the RPC boundary - InvalidInputException and
 * InvalidOutputException wrap it.
 */
final readonly class Failure
{
    public function __construct(
        public Issues $issues,
    ) {
    }

    /**
     * The message the wrapping exceptions report, kept here so both of them describe a failure the
     * same way.
     */
    public function describe(): string
    {
        return "Validation failed: {$this->issues->serializeToCompleteString()}.";
    }

    public function isSuccess(): false
    {
        return false;
    }
}
