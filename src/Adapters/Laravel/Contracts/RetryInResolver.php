<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts;

use Throwable;

interface RetryInResolver
{
    /**
     * Given the throwable that surfaced as RATE_LIMITED, return the seconds until a retry
     * may succeed, or null when unknown. Resolved from the container once at server build.
     */
    public function resolveRetryInSeconds(Throwable $throwable): ?int;
}
