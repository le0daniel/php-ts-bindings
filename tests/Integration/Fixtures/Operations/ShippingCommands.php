<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Operations;

use DateTime;
use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Middleware;
use Tests\Integration\Fixtures\MetadataMiddleware;
use Tests\Integration\Fixtures\Types\Address;
use Tests\Integration\Fixtures\Types\Batch;
use Tests\Integration\Fixtures\Types\Currency;
use Tests\Integration\Fixtures\Types\HandlingInstructions;
use Tests\Integration\Fixtures\Types\HomeDelivery;
use Tests\Integration\Fixtures\Types\Money;
use Tests\Integration\Fixtures\Types\OrderNumber;
use Tests\Integration\Fixtures\Types\PickupPoint;
use Tests\Integration\Fixtures\Types\PlaceOrderInput;
use Tests\Integration\Fixtures\Types\PublicWarehouse;
use Tests\Integration\Fixtures\Types\Sku;

/**
 * Casting edge shapes: property hooks, castable unions, generics, projections on the input
 * side, and the nullable/optional interplay. Handlers stay pure functions of their input.
 */
final class ShippingCommands
{
    /**
     * Property hooks and asymmetric visibility end to end: raw is INPUT only (its set hook
     * fills checksum), summary and checksum are OUTPUT only, code flows both ways.
     *
     * @param  array{instructions: HandlingInstructions}  $input
     */
    #[Command('shipping')]
    #[Middleware(MetadataMiddleware::class)]
    public function registerHandling(array $input): HandlingInstructions
    {
        return $input['instructions'];
    }

    /**
     * An undiscriminated union of castable classes with disjoint properties, next to a mutable
     * DateTime output (ATOM) and a DateTimeString with a non-default format.
     *
     * @param  array{destination: PickupPoint|HomeDelivery, window: DateTimeString<'d.m.Y H:i'>}  $input
     * @return array{destination: PickupPoint|HomeDelivery, eta: DateTime, window: DateTimeString<'d.m.Y H:i'>}
     */
    #[Command('shipping')]
    #[Middleware(MetadataMiddleware::class, ['value' => 'wow'])]
    public function scheduleDelivery(array $input): array
    {
        return [
            'destination' => $input['destination'],
            'eta' => new DateTime('2024-07-01T08:00:00+00:00'),
            'window' => $input['window'],
        ];
    }

    /**
     * A generic castable bound to a different type argument per direction: value objects in,
     * castables out.
     *
     * @param  Batch<Sku>  $input
     * @return Batch<Money>
     */
    #[Command('shipping')]
    public function dispatchBatch(Batch $input): Batch
    {
        return new Batch(
            count: $input->count,
            items: array_map(static fn (Sku $sku): Money => new Money(500, Currency::CHF), $input->items),
        );
    }

    /**
     * The Named attribute on PublicWarehouse is codegen-only: this round-trip pins that it has
     * zero runtime effect on either the eager or the cached registry.
     *
     * @param  array{warehouse: PublicWarehouse}  $input
     */
    #[Command('shipping')]
    public function renameWarehouse(array $input): PublicWarehouse
    {
        return $input['warehouse'];
    }

    /**
     * Pick and Omit on the INPUT side: the projections rebuild the classes as plain object
     * shapes, so the handler receives stdClass instances, never the original classes.
     *
     * @param  array{header: Pick<PlaceOrderInput, 'currency'>, partial: Omit<Address, 'company'>}  $input
     * @return array{city: string, currency: Currency}
     */
    #[Command('shipping')]
    public function updateManifest(array $input): array
    {
        return [
            'city' => $input['partial']->city,
            'currency' => $input['header']->currency,
        ];
    }

    /**
     * Castables nested inside a listed struct so a bad zip reports at shipments.0.address.zip.
     *
     * @param  array{shipments: non-empty-list<array{address: Address, ref: non-empty-string}>}  $input
     * @return array{count: int}
     */
    #[Command('shipping')]
    public function estimateCost(array $input): array
    {
        return ['count' => count($input['shipments'])];
    }

    /**
     * An optional-and-nullable key next to a required value-object-or-null union: absent,
     * null and present are three distinguishable states.
     *
     * @param  array{note?: string|null, reference: OrderNumber|null}  $input
     * @return array{hadNote: bool, note: string|null, reference: string|null}
     */
    #[Command('shipping')]
    public function applyCredit(array $input): array
    {
        return [
            'hadNote' => array_key_exists('note', $input),
            'note' => $input['note'] ?? null,
            'reference' => $input['reference']?->toStringValue(),
        ];
    }

    /**
     * A nullable DateTimeString output. The ORD-BAD branch deliberately returns a plain string
     * that violates both union arms: the server must answer 500 and never degrade to null,
     * because partial failure serialization is off for envelopes.
     *
     * @param  array{orderNumber: non-empty-string}  $input
     * @return array{until: DateTimeString<'Y-m-d'>|null}
     */
    #[Command('shipping')]
    public function holdShipment(array $input): array
    {
        return [
            'until' => match ($input['orderNumber']) {
                'ORD-BAD' => 'not-a-date',
                'ORD-HOLD' => new DateTimeImmutable('2024-09-15T00:00:00+00:00'),
                default => null,
            },
        ];
    }

    /**
     * A branded string inside a non-empty list: brands are codegen metadata, the runtime sees
     * plain strings and the list-length constraint.
     *
     * @param  array{tags: non-empty-list<BrandedString<'shipmentTag'>>}  $input
     * @return array{tags: non-empty-list<BrandedString<'shipmentTag'>>}
     */
    #[Command('shipping')]
    public function tagShipment(array $input): array
    {
        return $input;
    }

    /**
     * The ?T prefix sugar next to the spelled-out T|null union: identical runtime behavior.
     *
     * @param  array{legacyNote: ?string, modernNote: string|null}  $input
     * @return array{legacyNote: ?string, modernNote: string|null}
     */
    #[Command('shipping')]
    public function annotateShipment(array $input): array
    {
        return $input;
    }

    /**
     * Optional keys with complex values: a castable class and a literal union, both absent or
     * both present.
     *
     * @param  array{fallbackAddress?: Address, priority?: 1|2|3}  $input
     * @return array{hasFallback: bool, priority: int}
     */
    #[Command('shipping')]
    public function optionalExtras(array $input): array
    {
        return [
            'hasFallback' => array_key_exists('fallbackAddress', $input),
            'priority' => $input['priority'] ?? 2,
        ];
    }
}
