<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

enum IssueMessage: string
{
    case INVALID_TYPE = 'validation.invalid_type';
    case INVALID_KEY_TYPE = 'validation.invalid_key_type';
    case MISSING_PROPERTY = 'validation.missing_property';
    case FALSY_STRING = 'validation.falsy_string';
    case NOT_EMPTY_STRING = 'validation.not_empty_string';
    case NOT_NUMERIC_STRING = 'validation.not_numeric_string';
    case NOT_LOWERCASE_STRING = 'validation.not_lowercase_string';
    case NOT_UPPERCASE_STRING = 'validation.not_uppercase_string';
    case INTERNAL_ERROR = 'internal_error';
    case INVALID_MIN = 'validation.invalid_min';
    case INVALID_MAX = 'validation.invalid_max';
}
