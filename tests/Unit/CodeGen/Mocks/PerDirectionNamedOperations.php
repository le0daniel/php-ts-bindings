<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Mocks\Named\Customer;
use Tests\Mocks\Named\PerDirectionNamed;

/**
 * Two ends of the same rule: PerDirectionNamed has two shapes and a name per direction, so both are
 * declared; Customer has one shape, so its single alias is referenced on the way in and out alike.
 */
final class PerDirectionNamedOperations
{
    /**
     * @param PerDirectionNamed $input
     * @return PerDirectionNamed
     */
    #[Query('articles')]
    public function roundtrip(PerDirectionNamed $input): PerDirectionNamed
    {
        return $input;
    }

    /**
     * @param Customer $input
     * @return Customer
     */
    #[Query('articles')]
    public function customer(Customer $input): Customer
    {
        return $input;
    }
}
