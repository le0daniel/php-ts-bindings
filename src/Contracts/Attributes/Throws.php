<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use ReflectionClass;
use Throwable;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Throws
{
    public ?ErrorType $type;

    /**
     * @param class-string<Throwable> $exceptionClass
     * @param non-empty-string|null $name
     */
    public function __construct(
        public string  $exceptionClass,
        ?ErrorType     $type = null,
        public ?string $name = null,
    ) {
        $this->type = $type ?? ($this->name ? ErrorType::DOMAIN_ERROR : null);
    }

    /**
     * @internal
     */
    public function requiresThrowableReflection(): bool
    {
        return $this->type === null;
    }

    /**
     * @internal
     */
    public function getExposedAsOrNullThroughReflection(): ExposeAs|null
    {
        /** @var ReflectionClass<Throwable> $reflection */
        $reflection = new ReflectionClass($this->exceptionClass);
        $attribute = $reflection->getAttributes(ExposeAs::class);
        if (count($attribute) !== 1) {
            return null;
        }

        /** @var ExposeAs $instance */
        $instance = $attribute[0]->newInstance();
        return $instance;
    }

    /**
     * @internal
     */
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
