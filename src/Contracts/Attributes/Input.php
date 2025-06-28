<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Input
{
    public function __construct()
    {
    }
}