<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Closure;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;

/**
 * Brands the generated TypeScript of a class, interface, enum or value object, so that a
 * structurally identical value can no longer be passed where this type is expected. The emitted
 * type becomes `(... & Brand<"name">)`, INLINE at every use site — a brand alone declares no alias.
 * Combine with #[Named] to export it once by name: `export type UserId = (number & Brand<"userId">)`.
 *
 * The tag comes from one of three sources:
 *  - no name: lcfirst() of the base class name, so UserId becomes "userId";
 *  - a string: used verbatim;
 *  - a Closure(string $className): string, called with the class being emitted. PHP only accepts
 *    first-class callable syntax here, never a closure literal:
 *    #[Brand(name: BrandNaming::prefixed(...))]
 *
 * On a VALUE OBJECT the attribute may also be declared one level up, on the interface or parent
 * class a family of ids shares, and every child picks it up — deriving its own tag from its own
 * name, so siblings stay distinct types. Resolution order is: the class itself, then its direct
 * parent class, then its directly declared interfaces; two interfaces declaring the same attribute
 * are ambiguous and rejected. An inherited declaration may not carry a plain string name (every
 * child would share the one tag) — pass a Closure to compute one per class instead. One level
 * only: a grandparent, or an interface reached through another interface, is not consulted. Plain
 * classes and enums read the attribute from the class itself only.
 *
 * Brands are code generation metadata only: they have zero runtime impact, values travel the wire
 * in their plain shape, and the metadata never enters a cached AST.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Brand
{
    /**
     * @param string|Closure(string): string|null $name
     */
    public function __construct(
        public string|Closure|null $name = null,
    )
    {
    }

    public function brandName(string $classString): string
    {
        $name = match (true) {
            $this->name === null => explode('\\', $classString)
                    |> array_last(...)
                    |> lcfirst(...),
            $this->name instanceof Closure => ($this->name)($classString),
            default => $this->name,
        };

        if (!Syntax::isValidIdentifier($name)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($name, "#[Brand] on {$classString}");
        }

        return $name;
    }
}
