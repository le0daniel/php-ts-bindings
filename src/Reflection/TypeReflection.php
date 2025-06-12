<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Reflection;

use ReflectionParameter;
use ReflectionProperty;

final readonly class TypeReflection
{
    public function __construct(
        public string $type
    )
    {
    }

    // public static function fromReflection(ReflectionProperty|ReflectionParameter $reflection): self
    // {
    //
    // }
}