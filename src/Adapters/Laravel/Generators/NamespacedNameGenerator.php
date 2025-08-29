<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Generators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\FunctionNameGenerator;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\OperationDefinition;

final class NamespacedNameGenerator implements FunctionNameGenerator
{

    public function generateName(OperationDefinition $definition): string
    {
        $parts = [
            ...$this->splitNonAlphanumericParts($definition->namespace),
            ...$this->splitNonAlphanumericParts($definition->name),
        ];

        $joinedString = implode('', array_map(fn(string $part) => ucfirst($part), $parts));
        return lcfirst($joinedString);
    }

    /**
     * @param string $string
     * @return list<string>
     */
    private function splitNonAlphanumericParts(string $string): array
    {
        $parts = preg_split("/[^a-zA-Z0-9]+/", $string);
        if (!is_array($parts)) {
            throw new \RuntimeException("Could not split string into parts");
        }
        return $parts;
    }
}