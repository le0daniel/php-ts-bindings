<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Throwable;

interface ClientAwareException extends Throwable
{
    /**
     * Returns the name of the exception.
     * Example: "InvalidInput"|"EmailAlreadyExists"
     *
     * Needs to be static to analyze it correctly. Dynamic names are not supported.
     * This will help the frontend to generate the correct types.
     *
     * @return string
     */
    public static function name(): string;

    /**
     * Return a code to identify this exception.
     * @return int
     */
    public static function code(): int;

    /**
     * Serialize any data that might be relevant for the client.
     * Type the return value so that this can be used as the contract.
     *
     * Importantly, this data is expected to be an array. We type it but do not
     * run it through the parser. Meaning, the data is plainly exposed to the client.
     *
     * @return array<int|string, mixed>
     */
    public function serializeToResult(): array;
}