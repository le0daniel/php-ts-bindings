<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

enum IssueMessage: string
{
    case INVALID_TYPE = 'validation.invalid_type';
    case FALSY_STRING = 'validation.falsy_string';
    case INVALID_EMAIL = 'validation.invalid_email';
}
