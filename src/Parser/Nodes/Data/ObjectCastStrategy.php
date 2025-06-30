<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum ObjectCastStrategy
{
    case CONSTRUCTOR;
    case ASSIGN_PROPERTIES;
    case COLLECTION;
}