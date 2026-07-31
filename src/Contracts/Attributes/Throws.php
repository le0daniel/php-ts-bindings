<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Throwable;

/**
 * Used to declare which exceptions an endpoint can throw. If no exception is explicitly declared,
 * a 500 Internal Server error is returned to the client.
 *
 * Declared exceptions are only exposed to the client if their class is marked with the ExposeAs attribute.
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::IS_REPEATABLE)]
final readonly class Throws
{
    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function __construct(
        public string $exceptionClass,
    )
    {
    }
}