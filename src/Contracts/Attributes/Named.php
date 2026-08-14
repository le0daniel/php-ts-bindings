<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Closure;
use Le0daniel\PhpTsBindings\Data\IO;
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
 *  - a Closure(string $className, IO $io): string, called once per direction with the class being
 *    emitted. PHP only accepts first-class callable syntax here, never a closure literal:
 *    #[Named(name: AliasNaming::suffixed(...))]
 *
 * One name covers input and output alike. A class can legitimately have a different input shape
 * than output shape (constructor-only parameters, output-only properties), and one alias cannot
 * describe both honestly — every alias is declared exactly once in the generated types file. That
 * combination is rejected by MetadataNode::validate(), which runs at schema generation. The way out
 * is a Closure returning a distinct name per IO, so each shape gets its own alias.
 *
 * Two classes resolving to the same name with different shapes fail generation with a conflicting
 * alias error, as does a name colliding with a declaration the generated types file always
 * contains (Brand, Result, ...).
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
     * @param  string|Closure(string, IO): string|null  $name
     */
    public function __construct(
        public string|Closure|null $name = null,
    ) {
    }

    /**
     * Called once per direction. Only the Closure form can tell them apart; a derived or explicit
     * name is the same string both ways.
     *
     * @internal
     */
    public function typeName(string $classString, IO $io): string
    {
        $name = match (true) {
            $this->name === null => explode('\\', $classString) |> array_last(...),
            $this->name instanceof Closure => ($this->name)($classString, $io),
            default => $this->name,
        };

        if (! Syntax::isValidIdentifier($name)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($name, "#[Named] on {$classString}");
        }

        return $name;
    }
}
