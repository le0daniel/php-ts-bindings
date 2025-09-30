<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

enum ErrorType: int
{
    case DOMAIN_ERROR = 400;
    case AUTHENTICATION_ERROR = 401;
    case AUTHORIZATION_ERROR = 403;
    case NOT_FOUND = 404;
    case INVALID_INPUT = 422;
    case INTERNAL_ERROR = 500;
}