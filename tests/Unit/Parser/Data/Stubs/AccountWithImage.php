<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

use Tests\Mocks\Image;

readonly class AccountWithImage extends AccountData
{
    public function __construct(
        AccountData $accountData,
        public Image $image,
    )
    {
        parent::__construct($accountData->id, $accountData->name);
    }
}