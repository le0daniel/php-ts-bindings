<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use UnitEnum;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Query
{
    public function __construct(
        public ?string              $name = null,
        public ?string              $description = null,
        public string|UnitEnum|null $namespace = null,
        public int                  $inputParameterIndex = 0,
    )
    {
    }

    public function namespaceAsString(): ?string
    {
        return $this->namespace instanceof UnitEnum ? $this->namespace->name : $this->namespace;
    }
}