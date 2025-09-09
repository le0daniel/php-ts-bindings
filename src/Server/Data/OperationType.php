<?php declare(strict_types=1);

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
}
