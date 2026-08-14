<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

/**
 * There is deliberately no constraint toggle here. Constraints prove refinements that PHPStan
 * expresses about untrusted INPUT; output has already been through static analysis. See
 * SchemaExecutor::executeSerialize().
 */
final readonly class SerializationOptions
{
    /**
     * @param  bool  $partialFailures  Substitute null wherever a value fails to serialize under a
     *                                 null-accepting union, and report the result as a Success carrying issues, rather than
     *                                 failing outright. Useful when you are serializing best-effort and will inspect
     *                                 Success::isPartial() yourself. The RPC server never enables it - see Server::execute() -
     *                                 because answering 200 with data the operation did not produce is not something a client
     *                                 can detect.
     */
    public function __construct(
        public bool $partialFailures = true,
    ) {
    }
}
