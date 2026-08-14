<?php

declare(strict_types=1);

namespace Tests\Mocks\ValueObjects;

use Le0daniel\PhpTsBindings\Contracts\ValueObjects\StringValueObject;
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

/**
 * Rejects with ValidationException rather than a bare InvalidArgumentException, so the messages it
 * names reach the client. Collects every reason at once to pin that a list becomes several issues.
 */
final readonly class ValidatedEmail implements StringValueObject
{
    private function __construct(public string $value)
    {
    }

    public static function fromStringValue(string $value): static
    {
        $messages = [];
        if ($value === '') {
            $messages[] = 'Email is required';
        }
        if (! str_contains($value, '@')) {
            $messages[] = 'Email must contain an @';
        }

        if ($messages !== []) {
            throw new ValidationException($messages, ['value' => $value]);
        }

        return new self($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
