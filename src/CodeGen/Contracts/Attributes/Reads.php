<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Utils\Strings;
use UnitEnum;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Reads
{
    public function __construct(
        public string|UnitEnum|\Stringable $namespace,
    )
    {
    }

    public function stringValue(): string
    {
        return Strings::toString($this->namespace);
    }
}