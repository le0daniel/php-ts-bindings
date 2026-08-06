<?php

declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks\TsOutput;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\AccountFilter;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\AccountLockedException;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\ProvisioningException;
use Tests\Unit\CodeGen\Mocks\TsOutput\Types\QuotaExceededException;

/**
 * The error end of the fixture: one operation whose exposed exceptions widen the DOMAIN_ERROR
 * details into a union, next to one that declares nothing and gets the six default branches.
 */
final class AccountOperations
{
    /**
     * @return array{id: positive-int, term: string}
     */
    #[Query('accounts')]
    #[Throws(AccountLockedException::class)]
    public function find(AccountFilter $input): array
    {
        return ['id' => 1, 'term' => $input->term];
    }

    /**
     * @param  array{id: positive-int}  $input
     * @return array{locked: true}
     */
    #[Command('accounts')]
    #[Throws(AccountLockedException::class)]
    #[Throws(QuotaExceededException::class)]
    #[Throws(ProvisioningException::class)]
    public function lock(array $input): array
    {
        return ['locked' => true];
    }

    /**
     * @param  array{id: positive-int}  $input
     * @return array{unlocked: true}
     */
    #[Command('accounts')]
    public function unlock(array $input): array
    {
        return ['unlocked' => true];
    }
}
