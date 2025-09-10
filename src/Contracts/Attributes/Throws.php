<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;

/**
 * Used to declare which exceptions an endpoint can throw. If no exception is explicitly declared,
 * a 500 Internal Server error is returned to the client.
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::IS_REPEATABLE)]
final class Throws
{
    /**
     * @param class-string<ClientAwareException> $exceptionClass
     */
    public function __construct(
        public string $exceptionClass,
    )
    {
    }
}