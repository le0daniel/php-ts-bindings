<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

readonly class FullAccount extends AccountWithImage
{
    public function __construct(
        AccountWithImage $accountWithImage,
        public string $description,
    )
    {
        parent::__construct($accountWithImage, $accountWithImage->image);
    }
}