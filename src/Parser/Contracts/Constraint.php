<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Contracts;

use Le0daniel\PhpTsBindings\Contracts\ExportableToPhpCode;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Stringable;

/**
 * A refinement that a PHPStan type expresses but the PHP type system does not: `positive-int`
 * is an `int` to PHP, `non-empty-list<T>` is an `array`. The leaf node proves the PHP type; the
 * constraint proves what PHPStan added on top of it.
 *
 * Constraints are constructed by the consumers in Le0daniel\PhpTsBindings\Parser\Consumers from
 * the type string alone. There is deliberately no way to attach one to a property that its type
 * does not declare, so the AST always corresponds to the type it claims to represent.
 *
 * They run when PARSING untrusted input, never when serializing output. See
 * SchemaExecutor::executeSerialize().
 *
 * __toString() is the label that appears in ConstraintNode's diagnostics, so it must name the
 * bounds a constraint carries: `IntRange(1, max)`, not just `IntRange`.
 */
interface Constraint extends ExportableToPhpCode, Stringable
{
    public function validate(mixed $value, ExecutionContext $context): bool;
}
