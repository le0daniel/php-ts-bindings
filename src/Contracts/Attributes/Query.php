<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use BackedEnum;
use StringBackedEnum;
use UnitEnum;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Query
{
    public function __construct(
        public string|UnitEnum|null $namespace = null,
        public ?string              $description = null,
        public ?string              $name = null,
    )
    {
    }

    public function namespaceAsString(): ?string
    {
        if (is_string($this->namespace) || is_null($this->namespace)) {
            return $this->namespace;
        }

        if ($this->namespace instanceof BackedEnum) {
            return (string) $this->namespace->value;
        }

        return $this->namespace->name;
    }
}