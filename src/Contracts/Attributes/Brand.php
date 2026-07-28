<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Utils\Syntax;

/**
 * Brands the generated TypeScript of a class, interface, enum or value object, so that a
 * structurally identical value can no longer be passed where this type is expected. The emitted
 * type becomes `(... & Brand<"name">)`, INLINE at every use site — a brand alone declares no alias.
 * Combine with #[Named] to export it once by name: `export type UserId = (number & Brand<"userId">)`.
 *
 * Without a name, the brand is lcfirst() of the base class name: UserId becomes "userId".
 *
 * Brands are code generation metadata only: they have zero runtime impact, values travel the wire
 * in their plain shape, and the metadata never enters a cached AST.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Brand
{
    public function __construct(
        public ?string $name = null,
    )
    {
    }

    public function brandName(string $classString): string
    {
        $name = $this->name ?? lcfirst(explode('\\', $classString) |> array_last(...));

        if (!Syntax::isValidIdentifier($name)) {
            throw InvalidStringLiteralException::notAValidTypescriptIdentifier($name, "#[Brand] on {$classString}");
        }

        return $name;
    }
}
