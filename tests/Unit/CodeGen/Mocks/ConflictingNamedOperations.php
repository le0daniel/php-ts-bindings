<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Mocks\Named\Customer;

/**
 * Two classes with the same resolved name (Customer) in output position: the run must fail with
 * a conflicting alias error instead of emitting two contradicting declarations.
 */
final class ConflictingNamedOperations
{
    /**
     * @param array{q: string} $input
     * @return Customer
     */
    #[Query('conflict')]
    public function a(array $input): Customer
    {
        return new Customer();
    }

    /**
     * @param array{q: string} $input
     * @return \Tests\Mocks\Named\Conflict\Customer
     */
    #[Query('conflict')]
    public function b(array $input): \Tests\Mocks\Named\Conflict\Customer
    {
        return new \Tests\Mocks\Named\Conflict\Customer();
    }
}
