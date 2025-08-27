<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum ObjectCastStrategy
{
    case CONSTRUCTOR;
    case ASSIGN_PROPERTIES;

    /**
     * Collection classes expect an array of this type.
     * Best is to not use it at all. And rely on native PHP types like list or array.
     */
    case COLLECTION;
    case NEVER;
}