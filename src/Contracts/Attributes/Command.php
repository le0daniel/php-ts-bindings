<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use UnitEnum;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Command
{
    public function __construct(
        public string|UnitEnum|null $namespace = null,
        public ?string              $description = null,
        public ?string              $name = null,
    )
    {
    }
}