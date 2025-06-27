<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts;

use UnitEnum;

interface Client
{
    /**
     * @param 'success'|'error'|'alert'|'info' $type
     * @param string $message
     * @return void
     */
    public function toast(string $type, string $message): void;

    public function redirect(string $url): void;

    public function hardRedirect(string $url): void;

    public function invalidate(UnitEnum|string $namespace, mixed... $key): void;
}