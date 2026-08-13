<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * Output-only class serving as the Pick/Omit target: projections must drop properties from the
 * wire shape while the handler keeps returning full instances.
 */
final readonly class CustomerProfile
{
    public function __construct(
        public string $email,
        public string $name,
        public string $notes,
        public string $tier,
    ) {
    }
}
