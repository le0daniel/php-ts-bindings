<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use stdClass;
use Tests\Integration\Fixtures\Types\OrderStatus;
use Tests\Integration\Fixtures\Types\PaymentMethod;

/**
 * @phpstan-type CardType array{cardNumber: string, kind: 'card'}
 * @phpstan-type IBanType array{iban: string, kind: 'invoice'}
 * @phpstan-type TwintType array{kind: 'twint', phone: string}
 */
final class CheckoutCommands
{
    /**
     * Discriminated union on the INPUT side: three inline shapes sharing the literal kind. The
     * output proves a plain backed enum serializes by case name, not backing value.
     *
     * @param CardType|TwintType|IBanType $input
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
     * @param  object{level: 1|2|3, status: OrderStatus::PAID|OrderStatus::PENDING}  $input
     * @return array{flagged: 'high'|'low', level: 1|2|3}
     */
    #[Command('checkout')]
    public function flagPriority(object $input): array
    {
        if (!$input instanceof stdClass) {
            throw new InvalidArgumentException('Expected object');
        }

        return [
            'flagged' => $input->level === 1 ? 'high' : 'low',
            'level' => $input->level,
        ];
    }
}
