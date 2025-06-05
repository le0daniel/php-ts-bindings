<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Definition;

enum TypeScriptKeywords: string
{
    case UNKNOWN = 'unknown';
    case STRING = 'string';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case ANY = 'any';
    case NEVER = 'never';
}