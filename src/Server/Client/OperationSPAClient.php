<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Client;

use Le0daniel\PhpTsBindings\Contracts\SerializableClient;
use Le0daniel\PhpTsBindings\Server\Data\Toast;
use Le0daniel\PhpTsBindings\Utils\Dicts;
use Le0daniel\PhpTsBindings\Utils\Strings;
use UnitEnum;

/**
 * @phpstan-type Redirect array{url: string, reload: bool}
 * @phpstan-type SerializedToast array{type: value-of<\Le0daniel\PhpTsBindings\Server\Data\ToastType>, message: string}
 */
final class OperationSPAClient implements SerializableClient
{
    use InteractsWithToasts;

    /** @var Redirect|null  */
    private ?array $redirect = null;

    /** @var list<Toast>|null  */
    private ?array $toasts = null;

    /** @var list<array<int, mixed>>|null  */
    private ?array $invalidations = null;

    public function toast(Toast $toast): void
    {
        $this->toasts ??= [];
        $this->toasts[] = $toast;
    }

    public function redirect(string $url, bool $reload = false): void
    {
        $this->redirect = [
            'url' => $url,
            'reload' => $reload,
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
     * @return array{redirect?: Redirect, toasts?: list<SerializedToast>, invalidations?: list<array<int, mixed>>, type: 'operations-spa'}|null
     */
    public function serializeToArray(): array|null
    {
        $data = Dicts::filterNullValues([
            'redirect' => $this->redirect,
            'toasts' => $this->toasts === null
                ? null
                : array_map(fn(Toast $toast): array => $toast->toArray(), $this->toasts),
            'invalidations' => $this->invalidations,
        ]);

        if (empty($data)) {
            return null;
        }

        return [
            ...$data,
            'type' => 'operations-spa',
        ];
    }
}
