<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;

/**
 * A named type containing other named types, a branded value object and a collection, so alias
 * emission has to recurse and the module referencing it imports the whole set.
 */
#[Named]
#[Castable]
final class Product
{
    public ProductId $id;
    public Sku $sku;
    public string $title;
    public Money $price;
    public Availability $availability;

    /** @var list<non-empty-string> */
    public array $tags;
}
