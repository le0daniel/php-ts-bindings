<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use UnitEnum;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Action
{
    public function __construct(
        public ?string              $name = null,
        public ?string              $description = null,
        public string|UnitEnum|null $namespace = null,
    )
    {
    }
}