<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Closure;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;

/**
 * Exports a class, interface, enum or value object as a named TypeScript type alias. Instead of
 * inlining the structure at every use site, the code generator emits `export type {$name} = ...`
 * once and references it by name — recursively, so a named type may contain other named or
 * branded types. Combine with #[Brand] for an aliased branded type.
 *
 * The alias comes from one of three sources:
 *  - no name: the class base name, used verbatim, so App\Data\Order becomes `Order`;
 *  - a string: used verbatim;
 *  - a Closure(string $className): string, called with the class being emitted. PHP only accepts
 *    first-class callable syntax here, never a closure literal:
 *    #[Named(name: AliasNaming::suffixed(...))]
 *
 * Two classes resolving to the same name with different shapes fail generation with a conflicting
 * alias error, as does a name colliding with a declaration the generated types file always
 * contains (Brand, Result, ...).
 *
 * $io decides which direction the name applies to and defaults to IO::OUTPUT, because a class can
 * legitimately have a different input shape than output shape (constructor-only parameters,
 * output-only properties). On the other direction the structure is inlined as if the attribute
 * were absent. IO::BOTH names both directions under the one alias — if the two shapes differ,
 * generation fails hard instead of emitting a lying type. On value objects and enums the default
 * is IO::BOTH instead: their input and output shapes are always identical.
 *
 * On a VALUE OBJECT the attribute may also be declared one level up, on the interface or parent
 * class a family of ids shares, and every child picks it up — deriving its own alias from its own
 * name, so siblings stay distinct types. Resolution order is: the class itself, then its direct
 * parent class, then its directly declared interfaces; two interfaces declaring the same attribute
 * are ambiguous and rejected. An inherited declaration may not carry a plain string name (every
 * child would claim the one alias) — pass a Closure to compute one per class instead. One level
 * only: a grandparent, or an interface reached through another interface, is not consulted. Plain
 * classes and enums read the attribute from the class itself only, so implementing a #[Named]
 * interface names nothing.
 *
 * Names are code generation metadata only: they have zero runtime impact and never enter a
 * cached AST. Generic collection classes cannot be named — their alias would collide across
 * element types.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Named
{
    /**
     * @param string|Closure(string): string|null $name
     */
    public function __construct(
        public string|Closure|null $name = null,
        public ?IO                 $io = null,
    )
    {
    }

    /**
     * @internal
     */
    public function typeName(string $classString): string
    {
        $name = match (true) {
            $this->name === null => explode('\\', $classString) |> array_last(...),
            $this->name instanceof Closure => ($this->name)($classString),
            default => $this->name,
        };

        if (!Syntax::isValidIdentifier($name)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($name, "#[Named] on {$classString}");
        }

        return $name;
    }
}
