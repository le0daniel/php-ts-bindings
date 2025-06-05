<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Data;

enum Value
{
    case INVALID;
    case UNDEFINED;

    public function asPhpValue(): null
    {
        return null;
    }
}
