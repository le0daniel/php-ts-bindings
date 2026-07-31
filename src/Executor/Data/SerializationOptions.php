<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

/**
 * There is deliberately no constraint toggle here. Constraints prove refinements that PHPStan
 * expresses about untrusted INPUT; output has already been through static analysis. See
 * SchemaExecutor::executeSerialize().
 */
final readonly class SerializationOptions
{
    public function __construct(
        public bool $partialFailures = true,
    )
    {
    }
}