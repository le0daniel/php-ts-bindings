<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures\Types;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;

/**
 * The Named attribute is codegen-only metadata carried by a MetadataNode the optimizer strips
 * from the cached AST, so a round-trip through this class must be byte-identical between the
 * eager and cached registries with zero effect on the runtime envelope.
 */
#[Castable(ObjectCastStrategy::CONSTRUCTOR)]
#[Named('WarehouseView')]
final readonly class PublicWarehouse
{
    public function __construct(
        public string $code,
        public string $region,
    ) {
    }
}
