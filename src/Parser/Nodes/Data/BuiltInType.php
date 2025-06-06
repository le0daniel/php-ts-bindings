<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum BuiltInType: string
{
    case STRING = 'string';
    case INT = 'int';
    case BOOL = 'bool';
    case NULL = 'null';
    case FLOAT = 'float';
    case MIXED = 'mixed';

    public static function is(string $type): bool
    {
        return in_array($type, ['string', 'int', 'bool', 'null', 'float', 'mixed'], true);
    }
}