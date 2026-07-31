<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Client;

use Le0daniel\PhpTsBindings\Contracts\SerializableClient;
use Le0daniel\PhpTsBindings\Server\Data\Toast;
use Le0daniel\PhpTsBindings\Utils\Dicts;
use Le0daniel\PhpTsBindings\Utils\Strings;
use Override;
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

    #[Override]
    public function toast(Toast $toast): void
    {
        $this->toasts ??= [];
        $this->toasts[] = $toast;
    }

    #[Override]
    public function redirect(string $url, bool $reload = false): void
    {
        $this->redirect = [
            'url' => $url,
            'reload' => $reload,
        ];
    }

    #[Override]
    public function invalidate(UnitEnum|string $namespace, ...$key): void
    {
        $this->invalidations ??= [];
        $this->invalidations[] = [Strings::toString($namespace), ...$key] |> array_values(...);
    }

    /**
     * @return array{redirect?: Redirect, toasts?: list<SerializedToast>, invalidations?: list<array<int, mixed>>, type: 'operations-spa'}|null
     */
    #[Override]
    public function serializeToArray(): array|null
    {
        if ($this->redirect === null && $this->toasts === null && $this->invalidations === null) {
            return null;
        }

        // Assembled key by key, in the order the wire shape declares, so the emitted payload is
        // byte comparable and the shape stays provable.
        $payload = [];
        if ($this->redirect !== null) {
            $payload['redirect'] = $this->redirect;
        }
        if ($this->toasts !== null) {
            $payload['toasts'] = array_map(fn(Toast $toast): array => $toast->toArray(), $this->toasts);
        }
        if ($this->invalidations !== null) {
            $payload['invalidations'] = $this->invalidations;
        }

        $payload['type'] = 'operations-spa';
        return $payload;
    }
}
