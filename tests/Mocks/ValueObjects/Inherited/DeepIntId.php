<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

/**
 * Carries no attributes of its own. IntId does, but from an implementor of this interface IntId is
 * two levels up and therefore out of reach.
 */
interface DeepIntId extends IntId
{
}
