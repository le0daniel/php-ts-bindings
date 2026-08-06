<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Client;

use Le0daniel\PhpTsBindings\Server\Data\Toast;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Override;

/**
 * Implements the per level toast helpers of the Client contract in terms of toast(), so that
 * an implementation only ever has to decide what to do with a Toast.
 */
trait InteractsWithToasts
{
    abstract public function toast(Toast $toast): void;

    #[Override]
    public function success(string $message): void
    {
        $this->toast(new Toast(ToastType::SUCCESS, $message));
    }

    #[Override]
    public function error(string $message): void
    {
        $this->toast(new Toast(ToastType::ERROR, $message));
    }

    #[Override]
    public function warning(string $message): void
    {
        $this->toast(new Toast(ToastType::WARNING, $message));
    }

    #[Override]
    public function alert(string $message): void
    {
        $this->toast(new Toast(ToastType::ALERT, $message));
    }

    #[Override]
    public function info(string $message): void
    {
        $this->toast(new Toast(ToastType::INFO, $message));
    }
}
