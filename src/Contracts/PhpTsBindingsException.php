<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Throwable;

/**
 * Implemented by everything this library throws, so a consumer can name one type in a catch block
 * instead of falling back to \Throwable and swallowing unrelated failures.
 *
 * A marker rather than a base class: the concrete exceptions keep whichever SPL parent fits them,
 * and third party code (a custom TypeConsumer, a Constraint) can opt its own exceptions into the
 * same catch without inheriting from us.
 *
 * Catch one of the three subsystem bases - ParserException, SchemaException, CodeGenException -
 * when the failing phase matters; catch this when it does not.
 *
 * ValidationException is the one exception outside those three, and deliberately so: the bases all
 * mean the library could not do its job, while a ValidationException means it did - a value object
 * rejected a value. Filing it under SchemaException would make catching a server fault also catch
 * a user input rejection.
 */
interface PhpTsBindingsException extends Throwable
{
}
