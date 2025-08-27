<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Client;

use JsonSerializable;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\Client;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use Le0daniel\PhpTsBindings\Utils\Strings;
use UnitEnum;

/**
 * @phpstan-type Redirect array{type: 'soft'|'hard', url: string}
 * @phpstan-type Toast array{type: 'success'|'error'|'alert'|'info', message: string}
 */
final class OperationSPAClient implements Client, JsonSerializable
{
    /** @var Redirect|null  */
    private ?array $redirect = null;

    /** @var list<Toast>|null  */
    private ?array $toasts = null;

    /** @var list<array<int, mixed>>|null  */
    private ?array $invalidations = null;

    public function toast(string $type, string $message): void
    {
        $this->toasts ??= [];
        $this->toasts[] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public function redirect(string $url): void
    {
        $this->redirect = [
            'url' => $url,
            'type' => 'soft',
        ];
    }

    public function hardRedirect(string $url): void
    {
        $this->redirect = [
            'url' => $url,
            'type' => 'hard',
        ];
    }

    public function invalidate(UnitEnum|string $namespace, ...$key): void
    {
        $this->invalidations ??= [];
        $this->invalidations[] = [
            Strings::toString($namespace),
            ... $key,
        ];
    }

    /**
     * @return array{redirect?: Redirect, toasts?: null|Toast[], invalidations?: list<list<mixed>>, type: 'operations-spa'}|null
     */
    public function jsonSerialize(): array|null
    {
        $data = Arrays::filterNullValues([
            'redirect' => $this->redirect,
            'toasts' => $this->toasts,
            'invalidations' => $this->invalidations,
        ]);


        // @phpstan-ignore-next-line empty.variable -- the array can be empty, the filterNullValues fn is not correctly typed.
        if (empty($data)) {
            return null;
        }

        return [
            ...$data,
            'type' => 'operations-spa',
        ];
    }
}