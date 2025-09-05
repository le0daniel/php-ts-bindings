<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface ExposesClientData
{
    /**
     * If present, the client will get additional data from this exception.
     * This is handled in the DomainExceptionPresenter. This presenter will add this as data to the details array.
     *
     * @return array<int|string, mixed>
     */
    public function serializeToResult(): array;
}