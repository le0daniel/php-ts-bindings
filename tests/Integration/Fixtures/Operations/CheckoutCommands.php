<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Tests\Integration\Fixtures\Types\OrderStatus;
use Tests\Integration\Fixtures\Types\PaymentMethod;

final class CheckoutCommands
{
    /**
     * Discriminated union on the INPUT side: three inline shapes sharing the literal kind. The
     * output proves a plain backed enum serializes by case name, not backing value.
     *
     * @param  array{cardNumber: string, kind: 'card'}|array{iban: string, kind: 'invoice'}|array{kind: 'twint', phone: string}  $input
     * @return array{method: PaymentMethod, reference: string}
     */
    #[Command('checkout')]
    public function payOrder(array $input): array
    {
        return match ($input['kind']) {
            'card' => ['method' => PaymentMethod::CARD, 'reference' => 'pay-card-'.substr($input['cardNumber'], -4)],
            'invoice' => ['method' => PaymentMethod::INVOICE, 'reference' => 'pay-invoice-'.substr($input['iban'], -4)],
            'twint' => ['method' => PaymentMethod::TWINT, 'reference' => 'pay-twint-'.substr($input['phone'], -3)],
        };
    }

    /**
     * Int-literal unions and enum-case literals as input types; literal unions on the output.
     *
     * @param  array{level: 1|2|3, status: OrderStatus::PAID|OrderStatus::PENDING}  $input
     * @return array{flagged: 'high'|'low', level: 1|2|3}
     */
    #[Command('checkout')]
    public function flagPriority(array $input): array
    {
        return [
            'flagged' => $input['level'] === 1 ? 'high' : 'low',
            'level' => $input['level'],
        ];
    }
}
