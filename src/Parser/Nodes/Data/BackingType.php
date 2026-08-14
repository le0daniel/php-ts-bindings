<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

/**
 * The primitive a value object travels as on the wire.
 */
enum BackingType: string
{
    case STRING = 'string';
    case INT = 'int';
}
