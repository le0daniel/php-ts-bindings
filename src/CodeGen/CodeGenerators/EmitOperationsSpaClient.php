<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Server\Data\ToastType;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Override;

/**
 * The frontend half of OperationSPAClient: the payload it serializes, and the one guard that gets
 * you to it.
 *
 * It stands alone on purpose. Client is an extension point, so the envelope declines to say what
 * sits in `__client` — an implementation with a different set of directives is a different file
 * next to this one, and dropping this generator drops nothing but the directives it describes.
 *
 * Readonly, and it depends on no other generator: it declares every type it names.
 */
final readonly class EmitOperationsSpaClient implements GeneratesLibFiles
{
    private const string CLIENT_FILE = 'client-operations-spa';

    /**
     * Not static: reaching this means declaring the dependency, and a declared dependency that is
     * not registered fails the run before a line is generated.
     *
     * @param list<string> $values
     * @param list<string> $types
     */
    public function importFromOperationsSpaClient(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::CLIENT_FILE),
            values: $values,
            types: $types,
        );
    }

    /**
     * @return array<string, TypescriptFile>
     */
    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        // Derived from the enum so the emitted union can never drift from what Client::toast accepts.
        $toastTypes = implode('|', array_map(
            fn(ToastType $type): string => "'{$type->value}'",
            ToastType::cases(),
        ));

        return [
            self::CLIENT_FILE => new TypescriptFile(<<<TypeScript
export type ClientToast = {type: {$toastTypes}; message: string;};
export type ClientRedirect = {url: string; reload: boolean;};
export type ClientInvalidation = [string, ...unknown[]];

/**
 * What OperationSPAClient writes into `__client`. Every directive is optional — a key is only
 * present when a handler called for it — and the discriminator is not, which is what makes the
 * payload recognisable among whatever else a `__client` key might hold.
 */
export type OperationsClientPayload = {
    type: "operations-spa";
    redirect?: ClientRedirect;
    toasts?: ClientToast[];
    invalidations?: ClientInvalidation[];
};

/**
 * Narrows a response to one carrying this client's directives.
 *
 * The discriminator is the whole check: the payload is assembled in one pass by
 * serializeToArray(), so a server that wrote `type` wrote the rest of it to the same schema.
 * Re-verifying each directive here would only describe the same server twice, and unknown keys
 * are ignored either way, so adding a directive stays backwards compatible.
 */
export function containsOperationSpaPayload<const T>(value: T): value is T & {__client: OperationsClientPayload} {
    if (!value || typeof value !== 'object' || !('__client' in value)) {
        return false;
    }

    const payload = value.__client;
    return !!payload
        && typeof payload === 'object'
        && (payload as Partial<OperationsClientPayload>).type === 'operations-spa';
}
TypeScript),
        ];
    }
}
