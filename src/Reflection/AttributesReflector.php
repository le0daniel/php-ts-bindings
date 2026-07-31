<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Parser\Exceptions\ParserException;
use ReflectionAttribute;

final readonly class AttributesReflector
{
    /**
     * @param list<ReflectionAttribute<object>> $attributes
     */
    public function __construct(private array $attributes)
    {
    }

    /**
     * @param class-string $attributeClass
     * @return bool
     */
    public function has(string $attributeClass): bool
    {
        return array_any($this->attributes, fn(ReflectionAttribute $attribute) => $attribute->name === $attributeClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T
     */
    public function getSingleInstance(string $attributeClass): object
    {
        $reflection = array_find($this->attributes, fn(ReflectionAttribute $attribute) => $attribute->name === $attributeClass);
        if (!$reflection) {
            throw new ParserException("Attribute {$attributeClass} not found");
        }

        /** @var T */
        return $reflection->newInstance();
    }

}