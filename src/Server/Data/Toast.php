<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Server\Data;

/**
 * The wire shape lives here and not on the client, so every Client implementation serializes
 * toasts identically. Mirrored in TypeScript by EmitTypes as ClientToast.
 */
final readonly class Toast
{
    public function __construct(
        public ToastType $type,
        public string $message,
    ) {
    }

    /**
     * @return array{type: value-of<ToastType>, message: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'message' => $this->message,
        ];
    }
}
