<?php declare(strict_types=1);

namespace Tests\Mocks;

enum ResultEnum
{
    public const string OTHER = 'other';

    case SUCCESS;
    case FAILURE;
}
