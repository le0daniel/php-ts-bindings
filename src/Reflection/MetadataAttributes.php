<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use ReflectionClass;

/**
 * Reads the codegen attributes (#[Brand], #[Named]) off a class and wraps the parsed node in a
 * MetadataNode when any is present. See MetadataNode: pure code generation metadata, zero
 * runtime effect.
 */
final readonly class MetadataAttributes
{
    /**
     * @param ReflectionClass<covariant object> $reflectionClass
     * @param IO $defaultIo The direction a #[Named] without an explicit io applies to. Value
     *        objects and enums pass IO::BOTH — their input and output shapes are always identical.
     */
    public static function wrap(NodeInterface $node, ReflectionClass $reflectionClass, IO $defaultIo = IO::OUTPUT): NodeInterface
    {
        $attributes = new AttributesReflector($reflectionClass->getAttributes());

        $named = $attributes->has(Named::class) ? $attributes->getSingleInstance(Named::class) : null;
        $brand = $attributes->has(Brand::class) ? $attributes->getSingleInstance(Brand::class) : null;

        if ($named === null && $brand === null) {
            return $node;
        }

        $className = $reflectionClass->getName();

        return new MetadataNode(
            $node,
            $named === null ? null : new NamedType($named->typeName($className), $named->io ?? $defaultIo),
            $brand?->brandName($className),
        );
    }
}
