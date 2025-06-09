<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface LeafNode
{
    /**
     * Parse an incoming value to the correct type. Returns Value::INVALID if the value is invalid.
     * Should not throw an exception. Parsing should handle input that arrives from JSON.
     *
     * @param mixed $value
     * @param ExecutionContext $context
     * @return mixed
     */
    public function parseValue(mixed $value, ExecutionContext $context): mixed;

    /**
     * Given any value, should return the correct representation of the value for JSON serialization.
     * Returns Value::INVALID if the value is invalid. All other values are considered valid.
     *
     * @param mixed $value
     * @param ExecutionContext $context
     * @return mixed
     */
    public function serializeValue(mixed $value, ExecutionContext $context): mixed;

    public function inputDefinition(): string;
    public function outputDefinition(): string;
}