<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

/**
 * The contrast case to Currency: backed, but NOT a StringValueObject, so the backing values are
 * invisible on the wire and the case names ("CARD") are what the client sees.
 */
enum PaymentMethod: string
{
    case CARD = 'card';
    case TWINT = 'twint';
    case INVOICE = 'invoice';
}
