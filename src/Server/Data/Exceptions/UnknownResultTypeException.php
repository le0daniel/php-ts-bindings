<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data\Exceptions;

use RuntimeException;
use Throwable;

final class UnknownResultTypeException extends RuntimeException
{


    public static function fromResult(mixed $result): Throwable
    {
        return new UnknownResultTypeException("Invalid result type returned: " . gettype($result));
    }

}