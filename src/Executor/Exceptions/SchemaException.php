<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Exceptions;

use Le0daniel\PhpTsBindings\Contracts\PhpTsBindingsException;
use RuntimeException;

/**
 * Running a value through a schema failed for a reason that is not the value's fault: the operation
 * could not be found, a middleware was not usable, or the handler returned something the output
 * schema cannot represent.
 *
 * A value that simply does not validate is not this - that is a Failure, which is returned rather
 * than thrown.
 */
class SchemaException extends RuntimeException implements PhpTsBindingsException
{
}
