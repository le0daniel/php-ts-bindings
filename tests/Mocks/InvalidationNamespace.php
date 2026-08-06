<?php

declare(strict_types=1);

namespace Tests\Mocks;

/**
 * Backed enum used as an invalidation namespace: Strings::toString resolves it to its value,
 * while a pure enum (see ResultEnum) resolves to its case name instead.
 */
enum InvalidationNamespace: string
{
    case USERS = 'users';
    case ORDERS = 'orders';
}
