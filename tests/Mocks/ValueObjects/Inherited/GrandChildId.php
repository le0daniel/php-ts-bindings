<?php declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

/**
 * BaseId carries the attributes but sits two levels up, so this one inherits nothing.
 */
final readonly class GrandChildId extends ChildId
{
}
