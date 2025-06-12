<?php declare(strict_types=1);

namespace Tests\Unit\Parser\Data\Stubs;

use Le0daniel\PhpTsBindings\Parser\ASTOptimizer as Optimizer;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

/**
 * @phpstan-import-type AddressInput from Address as AddressInputData
 * @phpstan-import-type ZIP from Address
 * @phpstan-type UserWithData array{id: int, name: string, age: int, address: AddressInputData}
 */
final class MyUserClass
{
    public function __construct(TypeParser $parser, Optimizer $ASTOptimizer)
    {

    }
}