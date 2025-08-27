<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Utils;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;

final class OperationDescription
{
    public static function describe(Operation $operation): string
    {
        $body = implode(
            PHP_EOL,
            self::mapForDocBlock([
                ucfirst($operation->definition->type) . ": {$operation->definition->fullyQualifiedName()}",

                ...self::onlyWhen(!empty($operation->definition->description), [
                    '',
                    $operation->definition->description,
                ]),

                ...self::onlyWhen(!empty($operation->definition->caughtExceptions), [
                    '',
                    '**Exceptions:**',
                    ... array_map(self::mapCaughtExceptions(...), $operation->definition->caughtExceptions),
                ]),

                '',
                '@php ' . $operation->definition->fullyQualifiedClassName . '::' . $operation->definition->methodName,
            ])
        );

        return '/**' . PHP_EOL . $body . PHP_EOL . ' */';
    }

    /**
     * @param class-string<ClientAwareException> $className
     * @return string
     */
    private static function mapCaughtExceptions(string $className): string
    {
        $type = $className::type();
        $code = $className::code();

        return " - {$type} ({$code}) in `{$className}`";
    }

    /**
     * @param bool $condition
     * @param list<mixed> $value
     * @return list<mixed>
     */
    private static function onlyWhen(bool $condition, array $value): array
    {
        return $condition ? $value : [];
    }

    /**
     * @param list<string|int> $lines
     * @return list<string|int>
     */
    private static function mapForDocBlock(array $lines): array
    {
        return array_map(fn(mixed $line) => ' * ' . $line, $lines);
    }
}