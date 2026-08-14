<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Data;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\Operation;
use Le0daniel\PhpTsBindings\Typescript\Data\Typescript;

final class TypedOperation
{
    public Definition $definition {
        get => $this->operation->definition;
    }

    public string $key {
        get => $this->operation->key;
    }

    /**
     * An operation without an input renders as the null type, and every generator that emits a
     * signature for it has to drop the argument.
     */
    public bool $hasInput {
        get => $this->inputDef->type !== 'null';
    }

    /**
     * The two schema derived definitions each carry their own registry with every alias they rely
     * on: what the operation's file imports, and what the generated types file declares (via the
     * run's shared registry).
     *
     * @param  string  $domainErrors  The TypeScript union of the names this operation exposed, e.g.
     *                                `"account_locked"|"quota_exceeded"`, or `never` where it exposed
     *                                nothing. Everything else about its error type is the server's,
     *                                and lives in the Failure the types file declares.
     */
    public function __construct(
        public readonly Typescript $inputDef,
        public readonly Typescript $outputDef,
        public readonly string $domainErrors,
        public readonly Operation $operation,
    ) {
    }

    /**
     * The aliases the operation's own file references, ready to import. Failure is not among them -
     * it is a declaration the types file always contains, not a registry entry - and every generated
     * module imports it unconditionally.
     *
     * @return list<string> sorted
     */
    public function usedAliases(): array
    {
        $aliases = array_values(array_unique([
            ...$this->inputDef->registry->usedAliases(),
            ...$this->outputDef->registry->usedAliases(),
        ]));
        sort($aliases);

        return $aliases;
    }
}
