<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use ReflectionAttribute;

final readonly class AttributesReflector
{
    /**
     * @param  list<ReflectionAttribute<object>>  $attributes
     */
    public function __construct(private array $attributes)
    {
    }

    /**
     * @param  class-string  $attributeClass
     */
    public function has(string $attributeClass): bool
    {
        return array_any($this->attributes, fn (ReflectionAttribute $attribute) => $attribute->name === $attributeClass);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attributeClass
     * @return T
     */
    public function getSingleInstance(string $attributeClass): object
    {
        return $this->firstInstanceOrNull($attributeClass)
            ?? throw new ParserException("Attribute {$attributeClass} not found");
    }

    /**
     * A single scan for callers that treat an absent attribute as a valid outcome, instead of
     * has() followed by getSingleInstance() walking the list twice.
     *
     * @template T of object
     *
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    public function firstInstanceOrNull(string $attributeClass): ?object
    {
        $reflection = array_find($this->attributes, fn (ReflectionAttribute $attribute) => $attribute->name === $attributeClass);

        /** @var T|null */
        return $reflection?->newInstance();
    }
}
