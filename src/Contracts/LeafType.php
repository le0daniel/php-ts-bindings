<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

interface LeafType
{
    /**
     * Parse an incoming value to the correct type. Returns Value::INVALID if the value is invalid.
     * Should not throw an exception. Parsing should handle input that arrives from JSON.
     *
     * @param mixed $value
     * @param $context
     * @return mixed
     */
    public function parseValue(mixed $value, $context): mixed;

    public function serializeValue(mixed $value, $context): mixed;
}