<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

/**
 * Both candidates carry #[Named]; the parent class is consulted before the interfaces.
 */
final readonly class ParentWinsId extends ParentWinsBase implements ParentWinsContract
{
}
