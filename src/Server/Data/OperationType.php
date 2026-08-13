<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

enum OperationType
{
    case QUERY;
    case COMMAND;

    /**
     * @return "query"|"command"
     */
    public function lowerCase(): string
    {
        return strtolower($this->name);
    }

    /**
     * How a registry keys one operation. A query and a command may share a namespace.name, so the
     * type is part of the key - and every registry has to spell it the same way, or a cache written
     * by one cannot be read by the other.
     */
    public function fullyQualifiedOperationKey(string $key): string
    {
        return "{$this->name}@{$key}";
    }
}
