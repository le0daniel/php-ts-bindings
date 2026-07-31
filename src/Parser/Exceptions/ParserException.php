<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Exceptions;

use Le0daniel\PhpTsBindings\Contracts\PhpTsBindingsException;
use RuntimeException;

/**
 * Turning a PHP type declaration into an AST failed: the type string is malformed, names something
 * that cannot be resolved, or describes a shape the library cannot represent.
 *
 * Thrown directly when nothing more specific applies; the named subclasses in this namespace carry
 * the cases worth catching on their own.
 */
class ParserException extends RuntimeException implements PhpTsBindingsException
{
}
