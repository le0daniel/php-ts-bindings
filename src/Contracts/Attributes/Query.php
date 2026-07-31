<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Utils\Strings;
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
        return $this->namespace !== null ? Strings::toString($this->namespace) : null;
    }
}