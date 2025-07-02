<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface ExposesClientData
{
    /**
     * Serialize any data that might be relevant for the client.
     * Type the return value so that this can be used as the contract.
     *
     * Importantly, this data is expected to be an array. We type it but do not
     * run it through the executor. Meaning, the data is plainly exposed to the client.
     *
     * This is useful for validation messages if you want to return, for example, field level messages
     * In this case, you might type it as array<string, string[]>
     *
     * @return array<int|string, mixed>
     */
    public function serializeToResult(): array;
}