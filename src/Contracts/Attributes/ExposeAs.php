<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ExposeAs
{
    public function __construct(
        public ErrorType $type = ErrorType::DOMAIN_ERROR,
        public ?string $name = null,
    ) {
    }

    public function isValid(): bool
    {
        if (
            ($this->type === ErrorType::DOMAIN_ERROR && $this->name === null) ||
            ($this->type !== ErrorType::DOMAIN_ERROR && $this->name !== null)
        ) {
            return false;
        }

        if ($this->type === ErrorType::INVALID_INPUT) {
            return false;
        }

        return true;
    }
}
