<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Data;

/**
 * The direction a type is generated for.
 *
 * A single schema can describe a different shape per direction: a constructor parameter that is
 * not promoted only exists on the way in, a public property assigned in the constructor body only
 * exists on the way out, and a class that cannot be constructed from user input has no input type
 * at all.
 *
 * Every generation pass runs for exactly one of these. A #[Named] alias is resolved for both, which
 * is what its naming closure receives this enum for.
 */
enum IO
{
    case INPUT;
    case OUTPUT;
}
