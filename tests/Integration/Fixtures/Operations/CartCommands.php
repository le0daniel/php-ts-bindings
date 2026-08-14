<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Tests\Integration\Fixtures\NoOpMiddleware;
use Tests\Integration\Fixtures\Types\Currency;
use Tests\Integration\Fixtures\Types\LineItemInput;
use Tests\Integration\Fixtures\Types\Money;

final class CartCommands
{
    /**
     * The Optional constructor param on LineItemInput defaults to null when the key is absent,
     * and both value objects unwrap back to plain scalars on the way out.
     *
     * @param  array{item: LineItemInput}  $input
     * @return array{
     *     count: int,
     *     items: list<array{note: string|null, quantity: int, sku: string}>
     * }
     */
    #[Middleware(NoOpMiddleware::class)]
    #[Command('cart')]
    public function addItem(array $input): array
    {
        $item = $input['item'];

        return [
            'count' => 1,
            'items' => [
                [
                    'note' => $item->note,
                    'quantity' => $item->quantity->toIntValue(),
                    'sku' => $item->sku->toStringValue(),
                ],
            ],
        ];
    }

    /**
     * A branded string input and a bounded-int refinement, which is checked on parse only.
     *
     * @param  array{code: BrandedString<'voucherCode'>, percent: int<1, 100>}  $input
     * @return array{applied: bool, discount: Money}
     */
    #[Command('cart')]
    public function applyVoucher(array $input): array
    {
        return [
            'applied' => true,
            'discount' => new Money($input['percent'] * 10, Currency::CHF),
        ];
    }

    /**
     * Strict Y-m-d parsing in, a tuple of derived dates out. The window derives from the parsed
     * input date, so the output stays a pure function of the input. The window is an unkeyed
     * tuple (array{A, B}) whose elements are generics — exercising tuple elements that span
     * more than one token.
     *
     * @param  array{date: DateTimeString<'Y-m-d'>}  $input
     * @return array{confirmed: DateTimeString<'Y-m-d'>, window: array{DateTimeString<'Y-m-d'>, DateTimeString<'Y-m-d'>}}
     */
    #[Command('cart')]
    public function setDeliveryDate(array $input): array
    {
        $date = $input['date'];

        return [
            'confirmed' => $date,
            'window' => [$date, $date->modify('+2 days')],
        ];
    }
}
