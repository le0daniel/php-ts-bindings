<?php declare(strict_types=1);

namespace Tests\Mocks\Named;

/**
 * Implements a #[Named] interface without carrying the attribute itself. Metadata inheritance is
 * scoped to value objects, so this must stay an unnamed, inlined struct.
 */
final class ArticleResource implements PublicResource
{
    public function __construct(
        public string $url,
        public string $title,
    )
    {
    }
}
