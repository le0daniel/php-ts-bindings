<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Data;

enum IssueMessage: string
{
    case INVALID_TYPE = 'validation.invalid_type';
    case INVALID_KEY_TYPE = 'validation.invalid_key_type';
    case MISSING_PROPERTY = 'validation.missing_property';
    case INVALID_DATE_FORMAT = 'validation.invalid_date_format';
    case FALSY_STRING = 'validation.falsy_string';
    case INVALID_EMAIL = 'validation.invalid_email';
    case INTERNAL_ERROR = 'internal_error';
    case INVALID_MIN = 'validation.invalid_min';
    case INVALID_MAX = 'validation.invalid_max';
}
