<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Exceptions;

use Le0daniel\PhpTsBindings\Contracts\PhpTsBindingsException;
use RuntimeException;

/**
 * Emitting TypeScript failed: a node has no TypeScript representation, a generated name is not a
 * valid identifier, a generator's dependencies cannot be satisfied, or the output could not be
 * written.
 *
 * These surface at build time, never while serving a request.
 */
class CodeGenException extends RuntimeException implements PhpTsBindingsException
{
}
