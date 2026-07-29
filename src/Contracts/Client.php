<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts;

use Le0daniel\PhpTsBindings\Server\Data\Toast;
use UnitEnum;

interface Client
{
    public function toast(Toast $toast): void;

    public function success(string $message): void;

    public function error(string $message): void;

    public function warning(string $message): void;

    public function alert(string $message): void;

    public function info(string $message): void;

    /**
     * @param string $url
     * @param bool $reload Forces the client to do a full page load instead of a client side navigation.
     * @return void
     */
    public function redirect(string $url, bool $reload = false): void;

    /**
     * @param UnitEnum|string $namespace
     * @param mixed ...$key
     * @return void
     */
    public function invalidate(UnitEnum|string $namespace, mixed... $key): void;
}
