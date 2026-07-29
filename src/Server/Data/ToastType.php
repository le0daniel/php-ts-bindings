<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

enum ToastType: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case WARNING = 'warning';
    case ALERT = 'alert';
    case INFO = 'info';
}
