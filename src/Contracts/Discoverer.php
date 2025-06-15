<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use ReflectionClass;

interface Discoverer
{
    /**
     * @param ReflectionClass<object> $class
     * @return void
     */
    public function discover(ReflectionClass $class): void;
}