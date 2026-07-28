<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Data;

/**
 * The direction a type is generated for.
 *
 * A single schema can describe a different shape per direction: a constructor parameter that is
 * not promoted only exists on the way in, a public property assigned in the constructor body only
 * exists on the way out, and a class that cannot be constructed from user input has no input type
 * at all.
 *
 * BOTH is not a direction anything is generated for — TypescriptGenerator::toTypescript() rejects
 * it. It only exists as a #[Named] scope, saying the name applies to input and output alike.
 */
enum IO
{
    case INPUT;
    case OUTPUT;
    case BOTH;
}
