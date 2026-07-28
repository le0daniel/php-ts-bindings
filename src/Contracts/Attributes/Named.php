<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;

/**
 * Exports a class, interface, enum or value object as a named TypeScript type alias. Instead of
 * inlining the structure at every use site, the code generator emits `export type {$name} = ...`
 * once and references it by name — recursively, so a named type may contain other named or
 * branded types. Combine with #[Brand] for an aliased branded type.
 *
 * Without a name, the class base name is used verbatim: App\Data\Order becomes `Order`. Two
 * classes resolving to the same name with different shapes fail generation with a conflicting
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
 * Names are code generation metadata only: they have zero runtime impact and never enter a
 * cached AST. Generic collection classes cannot be named — their alias would collide across
 * element types.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Named
{
    public function __construct(
        public ?string $name = null,
        public ?IO     $io = null,
    )
    {
    }

    public function typeName(string $classString): string
    {
        $name = $this->name ?? (explode('\\', $classString) |> array_last(...));

        if (!Syntax::isValidIdentifier($name)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($name, "#[Named] on {$classString}");
        }

        return $name;
    }
}
