<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Mocks;

use Countable;
use Stringable;
use Tests\Mocks\Named\Conflict\Customer;

/**
 * Every member here is deliberately free of a PHPDoc type, so reflection is the only source. The
 * classes referenced live outside this namespace and, except for Customer, are not imported: that
 * is the shape that used to be resolved a second time against this file's namespace.
 */
final class NativeTypesMock
{
    public \DateTimeInterface $unimported;

    public Customer $imported;

    public ?Customer $nullableClass = null;

    public string $builtin = '';

    public ?string $nullableBuiltin = null;

    public mixed $anything = null;

    public function outOfNamespace(): \Tests\Mocks\Named\Conflict\Customer
    {
        throw new \Exception();
    }

    public function nullableClass(): ?\Tests\Mocks\Named\Conflict\Customer
    {
        throw new \Exception();
    }

    public function explicitNullUnion(): \Tests\Mocks\Named\Conflict\Customer|null
    {
        throw new \Exception();
    }

    public function union(): \Tests\Mocks\Named\Customer|\Tests\Mocks\Named\Conflict\Customer
    {
        throw new \Exception();
    }

    public function mixedUnion(): \Tests\Mocks\Named\Customer|string
    {
        throw new \Exception();
    }

    public function intersection(): Countable&Stringable
    {
        throw new \Exception();
    }

    public function disjunctiveNormalForm(): (Countable&Stringable)|null
    {
        throw new \Exception();
    }

    public function builtin(): string
    {
        throw new \Exception();
    }

    public function nullableBuiltin(): ?string
    {
        throw new \Exception();
    }

    public function anything(): mixed
    {
        throw new \Exception();
    }

    public function nothing(): void
    {
        throw new \Exception();
    }

    public function itself(): self
    {
        throw new \Exception();
    }

    public function parameters(\Tests\Mocks\Named\Conflict\Customer $customer, ?string $label): void
    {
        throw new \Exception();
    }
}
