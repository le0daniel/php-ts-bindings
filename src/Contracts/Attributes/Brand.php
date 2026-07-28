<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;

/**
 * Marks a value object as branded in the generated TypeScript, so that a bare string or number
 * can no longer be passed where the value object is expected.
 *
 * Without a name, the brand is lcfirst() of the base class name: UserId becomes "userId". The
 * emitted alias is then `export type UserId = number & Brand<"userId">`, because the code
 * generator capitalizes the brand when naming the alias.
 *
 * Brands are code generation metadata only. They have no runtime impact and are stripped
 * entirely when `operations:codegen` runs with --no-branded-types.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Brand
{
    public function __construct(
        public ?string $name = null,
    )
    {
    }
}
