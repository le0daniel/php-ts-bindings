<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput;

use DateTimeImmutable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\Draft;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\Money;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\Product;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\ProductId;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\Sku;

/**
 * The named and branded end of the fixture: aliases declared once in lib/types.ts and imported by
 * every module that mentions them, including one class that needs an alias per direction.
 */
final class CatalogOperations
{
    /**
     * @param array{id: ProductId} $input
     */
    #[Query('catalog')]
    public function product(array $input): Product
    {
        return new Product();
    }

    /**
     * @param array{term: non-empty-string, availability?: Types\Availability, limit?: int<1, 100>} $input
     * @return array{results: list<Product>, total: non-negative-int}
     */
    #[Query('catalog')]
    public function search(array $input): array
    {
        return ['results' => [], 'total' => 0];
    }

    /**
     * Two names for one class: the input carries the title the constructor takes, the output the
     * slug the class exposes.
     *
     * Not called `draft`: an operation declares {Name}Input and {Name}Result in its own module, and
     * `draft` would collide there with the imported Draft/DraftInput aliases. That collision is
     * intentionally left to the TypeScript compiler rather than guarded in PHP, so the fixture
     * simply does not write it.
     */
    #[Query('catalog')]
    public function prepare(Draft $input): Draft
    {
        return $input;
    }

    /**
     * @param array{sku: Sku, amount: positive-int, price: Money} $input
     * @return array{product: Product, restockedAt: DateTimeImmutable}
     */
    #[Command('catalog')]
    public function restock(array $input): array
    {
        return ['product' => new Product(), 'restockedAt' => new DateTimeImmutable()];
    }
}
