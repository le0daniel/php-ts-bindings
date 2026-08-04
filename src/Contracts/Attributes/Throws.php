<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Throwable;

/**
 * Used to declare which exceptions an endpoint can throw. If no exception is explicitly declared,
 * a 500 Internal Server error is returned to the client.
 *
 * A declared exception is only exposed to the client once it has a name to be exposed under. That
 * name comes from `as`, or - when `as` is omitted - from the ExposeAs attribute on the exception
 * class itself. `as` always wins, and exposes the exception whether or not its class carries
 * ExposeAs: the exception may be one you cannot annotate, or one worth naming differently here.
 * An exception with neither stays a 500.
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::IS_REPEATABLE)]
final readonly class Throws
{
    /**
     * @param class-string<Throwable> $exceptionClass
     * @param non-empty-string|null $as
     */
    public function __construct(
        public string $exceptionClass,
        public ?string $as = null,
    )
    {
    }
}
