<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * Not an enum on purpose: the constants exist so ShippingClass::EXPRESS in a docblock exercises
 * the class-constant literal path (ClassConstConsumer), which resolves the constant's value into
 * a plain string literal at parse time.
 */
final class ShippingClass
{
    public const string EXPRESS = 'express';

    public const string STANDARD = 'standard';
}
