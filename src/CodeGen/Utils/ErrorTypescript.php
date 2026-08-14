<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Errors\ThrowAttributeResolver;
use ReflectionException;

/**
 * The one piece of error TypeScript that varies per operation: the literal union of domain error
 * names its scopes expose. The static error catalogue - the envelope declarations and the Failure
 * union - lives directly in EmitTypes, next to the file that declares it.
 *
 * The runtime counterpart is the Server itself, which resolves the category from the throwing
 * scope's #[Throws] declarations and falls back to Server\Errors\ErrorClassifier.
 */
final readonly class ErrorTypescript
{
    /**
     * The literal union one operation instantiates the domain branch with, or `never` where it
     * exposes nothing - DomainError erases itself on that, so such an operation's Failure has no
     * 400 branch at all.
     *
     * @throws ReflectionException
     */
    public static function domainTypesFor(Definition $definition): string
    {
        $names = ThrowAttributeResolver::collectDomainErrorNamesFromDefinition($definition);
        if (count($names) === 0) {
            return 'never';
        }

        return implode('|', array_map(
            static fn (string $name): string => json_encode($name, JSON_THROW_ON_ERROR),
            $names,
        ));
    }
}
