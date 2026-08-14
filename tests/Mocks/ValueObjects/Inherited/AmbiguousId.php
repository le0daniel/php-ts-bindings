<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects\Inherited;

/**
 * Invalid on purpose: IntId and AlsoBranded both declare #[Brand], and nothing about the two says
 * which one should win. Picking the first would be the library guessing.
 */
final readonly class AmbiguousId implements AlsoBranded, IntId
{
    private function __construct(public int $value)
    {
    }

    public static function fromIntValue(int $value): static
    {
        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
