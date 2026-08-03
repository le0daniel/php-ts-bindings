<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

use InvalidArgumentException;
use function gettype;

final readonly class Assertions
{
    /**
     * @template TInstance
     * @param class-string<TInstance> $className
     * @param mixed $value
     * @phpstan-assert TInstance $value
     * @return TInstance
     */
    public static function instanceOf(string $className, mixed $value): mixed
    {
        if (!$value instanceof $className) {
            throw new InvalidArgumentException(\sprintf('Expected instance of %s, got %s', $className, gettype($value)));
        }

        return $value;
    }
}