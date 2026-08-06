<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Named;
use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\NamedType;
use Le0daniel\PhpTsBindings\Parser\Nodes\MetadataNode;
use ReflectionClass;

/**
 * Reads the codegen attributes (#[Brand], #[Named]) off a class and wraps the parsed node in a
 * MetadataNode when any is present. See MetadataNode: pure code generation metadata, zero
 * runtime effect.
 */
final readonly class MetadataAttributes
{
    /**
     * @param  ReflectionClass<covariant object>  $reflectionClass
     * @param  bool  $inheritFromParents  Also accept an attribute declared one level up, on the direct
     *                                    parent class or a directly declared interface. Value objects opt in so a family of ids
     *                                    can share one declaration; see wrap()'s callers.
     */
    public static function wrap(
        NodeInterface $node,
        ReflectionClass $reflectionClass,
        bool $inheritFromParents = false,
    ): NodeInterface {
        $attributes = new AttributesReflector($reflectionClass->getAttributes());

        // 1. The class itself. A local declaration always wins, and declaring both skips the
        //    lookup entirely.
        $named = $attributes->firstInstanceOrNull(Named::class);
        $brand = $attributes->firstInstanceOrNull(Brand::class);

        if ($inheritFromParents && ($named === null || $brand === null)) {
            // 2. The direct parent class, then 3. the directly declared interfaces.
            [$named, $brand] = self::inheritMissing($reflectionClass, $named, $brand);
        }

        if ($named === null && $brand === null) {
            return $node;
        }

        $className = $reflectionClass->getName();

        // Both directions are resolved here, so no user closure ever travels in the node tree. Only
        // the Closure form can return two different names; everything else lands on one alias, and
        // MetadataNode::validate() is what rejects that over a shape that differs per direction.
        return new MetadataNode(
            $node,
            $named === null ? null : new NamedType(
                inputName: $named->typeName($className, IO::INPUT),
                outputName: $named->typeName($className, IO::OUTPUT),
            ),
            $brand?->brandName($className),
        );
    }

    /**
     * Fills in whichever of the two the class did not declare itself, one level up. The parent
     * class is a single unambiguous candidate and is consulted first; the interfaces are a set, so
     * two of them declaring the same attribute is an ambiguity we refuse to resolve silently.
     *
     * @param  ReflectionClass<covariant object>  $reflectionClass
     * @return array{Named|null, Brand|null}
     */
    private static function inheritMissing(ReflectionClass $reflectionClass, ?Named $named, ?Brand $brand): array
    {
        $target = $reflectionClass->getName();

        // Only what the class left undeclared is looked up, so a candidate carrying an attribute
        // that is already resolved is never read and never validated.
        $parent = $reflectionClass->getParentClass();
        if ($parent !== false) {
            $parentAttributes = new AttributesReflector($parent->getAttributes());

            if ($named === null) {
                $named = $parentAttributes->firstInstanceOrNull(Named::class);
                self::assertInheritable($named, $parent->getName(), $target);
            }

            if ($brand === null) {
                $brand = $parentAttributes->firstInstanceOrNull(Brand::class);
                self::assertInheritable($brand, $parent->getName(), $target);
            }
        }

        if ($named !== null && $brand !== null) {
            return [$named, $brand];
        }

        $named ??= self::fromInterfaces($reflectionClass, Named::class, $target);
        $brand ??= self::fromInterfaces($reflectionClass, Brand::class, $target);

        return [$named, $brand];
    }

    /**
     * The interfaces are unordered as far as intent goes, so the first match is not a decision the
     * library gets to make on the author's behalf. Two of them carrying the attribute is rejected.
     *
     * @template T of Named|Brand
     *
     * @param  ReflectionClass<covariant object>  $reflectionClass
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    private static function fromInterfaces(ReflectionClass $reflectionClass, string $attributeClass, string $target): Named|Brand|null
    {
        $found = null;
        $declaredOn = null;

        foreach (self::directlyDeclaredInterfaces($reflectionClass) as $interface) {
            $instance = new AttributesReflector(new ReflectionClass($interface)->getAttributes())
                ->firstInstanceOrNull($attributeClass);

            if ($instance === null) {
                continue;
            }

            if ($found !== null) {
                $attributeName = $attributeClass === Named::class ? 'Named' : 'Brand';
                throw new ParserException(
                    "{$target} inherits #[{$attributeName}] from more than one interface ({$declaredOn} and {$interface}). "
                    ."Declare it on {$target} itself to say which one applies."
                );
            }

            $found = $instance;
            $declaredOn = $interface;
        }

        self::assertInheritable($found, (string) $declaredOn, $target);

        return $found;
    }

    /**
     * An inherited declaration has to produce a name per concrete class. A plain string would hand
     * every child the identical tag and collapse siblings into a single TypeScript type — silently
     * for #[Brand], and as a conflicting alias error far from the cause for #[Named]. A naming
     * Closure is exactly how you opt out of the default derivation while keeping them distinct.
     */
    private static function assertInheritable(Named|Brand|null $attribute, string $declaredOn, string $target): void
    {
        if ($attribute === null || ! is_string($attribute->name)) {
            return;
        }

        $attributeName = $attribute instanceof Named ? 'Named' : 'Brand';
        throw new ParserException(
            "#[{$attributeName}] on {$declaredOn} is inherited by {$target} and cannot carry a fixed name: "
            ."every child would share \"{$attribute->name}\". Drop the name to derive it per class, "
            ."or pass a closure: #[{$attributeName}(name: Naming::method(...))]."
        );
    }

    /**
     * One level up and no further: a grandparent, or an interface reached through another
     * interface, is never consulted.
     *
     * ReflectionClass::getInterfaceNames() is transitive — it also reports interfaces reached
     * through another interface or through the parent class, which sit two or more levels up.
     * Those are subtracted here, so only what the class itself declares remains.
     *
     * @param  ReflectionClass<covariant object>  $reflectionClass
     * @return list<class-string>
     */
    private static function directlyDeclaredInterfaces(ReflectionClass $reflectionClass): array
    {
        $all = $reflectionClass->getInterfaceNames();
        if ($all === []) {
            return [];
        }

        $parent = $reflectionClass->getParentClass();
        $indirect = $parent === false ? [] : $parent->getInterfaceNames();
        foreach ($all as $interface) {
            array_push($indirect, ...new ReflectionClass($interface)->getInterfaceNames());
        }

        return array_values(array_diff($all, $indirect));
    }
}
