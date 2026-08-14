<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Typescript\Exceptions;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;

/**
 * Thrown when a type alias is read out of the registry without being defined in it.
 */
final class UnknownAliasException extends CodeGenException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $knownAliases
     */
    public static function forAlias(string $alias, array $knownAliases): self
    {
        $known = $knownAliases === [] ? 'none' : implode(', ', $knownAliases);

        return new self(
            "Unknown type alias '{$alias}'. Call has() before get(). Known aliases: {$known}."
        );
    }
}
