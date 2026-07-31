<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Contracts;

use Le0daniel\PhpTsBindings\Executor\Data\Issues;

/**
 * The two possible outcomes of running a value through a schema: Success and Failure.
 *
 * Signatures keep spelling out the `Success|Failure` union so the narrow type survives into the
 * caller; this interface exists so code that only needs to know whether it went well - a logger, a
 * decorator, a test helper - does not have to instanceof its way there.
 *
 * A Failure is returned, never thrown. Mirrors the `Result<T, E> = Success<T> | Failure<E>` that
 * EmitTypes generates, so both sides of the binding describe the outcome the same way.
 */
interface Result
{
    public function isSuccess(): bool;

    /**
     * Present on both arms: a Success carries issues when it was parsed with partialFailures
     * enabled, so an empty Issues is not the same as success.
     */
    public function issues(): Issues;
}
