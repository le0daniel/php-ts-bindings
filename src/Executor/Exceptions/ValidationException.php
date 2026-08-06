<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Exceptions;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\PhpTsBindingsException;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use RuntimeException;

/**
 * Thrown by a value object factory to reject a value and say why.
 *
 * Without it, every Throwable a factory raises collapses to the single key
 * `validation.invalid_value` and the reason survives only in debug output, so a client cannot tell
 * an empty email from a malformed one. Each message here becomes its own Issue at the field's path
 * and reaches the client under `details.fields`.
 *
 * The messages are plain strings, on the wire exactly as written. Whether they are English, a
 * localization key, or anything else is the application's business - this library does not
 * translate. Anything that must NOT reach the client belongs in $debugInfo, which never leaves the
 * server outside of debug mode.
 *
 * Deliberately not a SchemaException: that base means a failure which is not the value's fault,
 * and this is precisely the value's fault. It is the one exception the library declares that sits
 * outside the three subsystem bases.
 */
final class ValidationException extends RuntimeException implements PhpTsBindingsException
{
    /**
     * @var list<string>
     */
    public readonly array $messages;

    /**
     * @param string|array<string> $messages Not narrowed to a list: collecting reasons with
     *                                       array_filter() leaves holes, and renumbering here is
     *                                       friendlier than making every caller remember to.
     * @param array<string, mixed> $debugInfo Server side only diagnostics; never sent to the client.
     */
    public function __construct(
        string|array       $messages,
        public readonly array $debugInfo = [],
    )
    {
        $this->messages = is_string($messages) ? [$messages] : array_values($messages);

        // A rejection with nothing to say would record no issue, and SchemaExecutor would answer
        // with a Failure whose issues map is empty - a 422 carrying `details.fields: {}`. Failing
        // here instead surfaces the mistake where it was made.
        if ($this->messages === []) {
            throw new InvalidArgumentException(
                'A ValidationException must carry at least one message, otherwise the value is rejected without a reason.',
            );
        }

        parent::__construct(implode(', ', $this->messages));
    }

    /**
     * @param array<string, mixed> $debugInfo Context from the call site, merged underneath the
     *                                        thrower's own entries.
     * @return list<Issue>
     */
    public function toIssues(array $debugInfo = []): array
    {
        $mergedDebugInfo = [...$debugInfo, ...$this->debugInfo];

        return array_map(
            fn(string $message): Issue => new Issue($message, $mergedDebugInfo, exception: $this),
            $this->messages,
        );
    }
}
