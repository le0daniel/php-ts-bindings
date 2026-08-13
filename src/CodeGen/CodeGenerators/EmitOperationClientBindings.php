<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;

use Le0daniel\PhpTsBindings\CodeGen\Contracts\DependsOn;
use Le0daniel\PhpTsBindings\CodeGen\Contracts\GeneratesLibFiles;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\Utils\Paths;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;
use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;
use Le0daniel\PhpTsBindings\Utils\Assertions;
use Override;

/**
 * Not readonly: the EmitTypes and EmitTypeUtils its files import from are injected after
 * construction, which is the only way they can be the same instances the generator runs.
 */
final class EmitOperationClientBindings implements DependsOn, GeneratesLibFiles
{
    private const string BINDINGS_FILE = 'bindings';

    private const string OPERATION_CLIENT_FILE = 'OperationClient';

    private const string DEFAULT_CLIENT_FILE = 'DefaultClient';

    private const string OPERATION_EXCEPTION_FILE = 'OperationException';

    private EmitTypes $types;

    private EmitTypeUtils $utils;

    /**
     * One method per file this generator writes, so nothing outside spells a file name it does not
     * own. Not static: reaching them means declaring the dependency, and a declared dependency that
     * is not registered fails the run before a line is generated.
     *
     * @param  list<string>  $values
     * @param  list<string>  $types
     */
    public function importFromBindings(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::BINDINGS_FILE),
            values: $values,
            types: $types,
        );
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $types
     */
    public function importFromOperationClient(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::OPERATION_CLIENT_FILE),
            values: $values,
            types: $types,
        );
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $types
     */
    public function importFromDefaultClient(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::DEFAULT_CLIENT_FILE),
            values: $values,
            types: $types,
        );
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $types
     */
    public function importFromOperationException(array $values = [], array $types = []): TypescriptImport
    {
        return new TypescriptImport(
            Paths::libImport(self::OPERATION_EXCEPTION_FILE),
            values: $values,
            types: $types,
        );
    }

    #[Override]
    public function dependsOnGenerator(): array
    {
        return [
            EmitTypes::class,
            EmitTypeUtils::class,
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->types = Assertions::instanceOf(
            EmitTypes::class,
            $dependencies[EmitTypes::class] ?? null,
        );
        $this->utils = Assertions::instanceOf(
            EmitTypeUtils::class,
            $dependencies[EmitTypeUtils::class] ?? null,
        );
    }

    /**
     * @return array<string, TypescriptFile>
     */
    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        return [
            self::OPERATION_CLIENT_FILE => new TypescriptFile(<<<'TypeScript'
export type OperationOptions = {signal?: AbortSignal; timeoutMs?: number; client?: OperationClient};

/**
 * Moves a request and resolves to the envelope. A server may put more next to the data, and it
 * travels through untouched — describing it here would tie every transport to one Client
 * implementation's schema. Reach for the guard the implementation ships instead.
 *
 * The only thing an operation adds to the error catalogue is which domain errors it exposed, so that
 * is all this takes. ClientError needs no mention: a request can fail before it reaches the server,
 * and Failure carries that branch whatever the operation declared.
 */
export interface OperationClient {
    execute<O, TDomainType extends string = never>(
        type: "command"|"query",
        key: string,
        input: unknown,
        options?: OperationOptions
    ): Promise<Result<O, TDomainType>>;
}
TypeScript, [
                $this->types->importFromTypes(types: ['Result']),
            ]),
            self::DEFAULT_CLIENT_FILE => new TypescriptFile(<<<'TypeScript'
/**
 * A hook sees the envelope of any operation, so it is typed against the widest domain union rather
 * than any one operation's. Every category is still there to discriminate on — the catalogue is the
 * server's, not the operation's.
 */
export type Hook = (result: Result<unknown, string>) => Promise<void> | void;

export class DefaultClient implements OperationClient {

    private hooks: Hook[] = [];

    constructor(
        private readonly fetcher: typeof window.fetch,
        private readonly options: {
            paths: { query: string; command: string; };
            baseUrl?: string;
            timeoutMs?: number;
        },
    ) {
    }

    private joinSignals(signals: (AbortSignal | null | undefined)[]): AbortSignal | undefined {
        const filtered = signals.filter((value: AbortSignal | null | undefined): value is AbortSignal => !!value);
        if (filtered.length === 0) {
            return undefined;
        }

        return filtered.length === 1 ? filtered[0] : AbortSignal.any(filtered);
    }

    private createJsonEncodedQueryParams(input: unknown): string {
        if (!input || typeof input !== 'object') {
            return '';
        }

        return Object.entries(input)
            .filter(([key, value]) => value !== undefined)
            .map(([key, value]) => {
                return `${encodeURIComponent(key)}=${encodeURIComponent(JSON.stringify(value))}`;
            }).join('&');
    }

    private async callHooks<const T extends Result<unknown, string>>(result: T) {
        try {
            await Promise.all(this.hooks.map(hook => hook(result)));
            return result;
        } catch (error) {
            console.error('Error while calling hooks', error);
            return result;
        }
    }

    async execute<O, TDomainType extends string = never>(type: "command" | "query", key: string, input: unknown, options?: OperationOptions): Promise<Result<O, TDomainType>> {
        const route = this.options.paths[type].substring(0, 1) === '/' ? this.options.paths[type].substring(1) : this.options.paths[type];
        const fullPath = `${this.options.baseUrl ?? ''}/${route.replace('{key}', key)}`;

        // Per call wins over the client wide default, and the timeout signal actually fires: a
        // fresh AbortController is never aborted by anything.
        const timeoutInMs = options?.timeoutMs ?? this.options?.timeoutMs;
        const signal = this.joinSignals([
            options?.signal,
            timeoutInMs ? AbortSignal.timeout(timeoutInMs) : undefined
        ]);

        const headers: Record<string, string> = {
            Accept: 'application/json',
            "X-Client-ID": "operations-spa"
        };

        if (type === 'command') {
            headers['Content-Type'] = 'application/json';
        }

        try {
            const queryParams = type === 'query' && input && typeof input === 'object'
                ? `?${this.createJsonEncodedQueryParams(input)}`
                : '';

            const response = await this.fetcher(`${fullPath}${queryParams}`, {
                method: type === 'query' ? 'GET' : 'POST',
                signal,
                headers,
                body: type === 'command' ? JSON.stringify(input) : undefined,
            });

            // The status line is never consulted: anything between the browser and the handler can
            // write one, so only the body can prove the server answered. A valid envelope is
            // returned exactly as parsed, success or failure — whatever the server put next to it
            // (a client's directives, say) rides along untouched.
            const json: unknown = await response.json().catch(() => undefined);
            if (isValidEnvelop(json)) {
                return await this.callHooks(json as Result<O, TDomainType>);
            }

            return await this.callHooks({
                success: false,
                code: 0,
                type: 'CLIENT_ERROR',
                cause: new Error(`Invalid response envelope (HTTP status ${response.status})`),
                response: json === undefined
                    ? {httpStatusCode: response.status}
                    : {httpStatusCode: response.status, jsonResponse: json},
            } satisfies Failure);
        } catch (e: unknown) {
            // Anything thrown between here and the response being read: the request never completed,
            // so there is no server error to report and the cause is the answer. It is carried as
            // itself rather than summarised — throwOnFailure rethrows an AbortError exactly, and a
            // re-wrapped copy would no longer be that DOMException.
            //
            // No type argument: this branch is in every Failure, whatever the operation exposed.
            const cause = e instanceof Error ? e : new Error(String(e));
            const envelop = {success: false, code: 0, type: 'CLIENT_ERROR', cause} satisfies Failure;
            return await this.callHooks(envelop);
        }
    }

    registerHook(hook: Hook): () => void {
        this.hooks.push(hook);
        return () => {
            this.hooks = this.hooks.filter(h => h !== hook);
        }
    }

}
TypeScript, [
                $this->importFromOperationClient(types: ['OperationClient', 'OperationOptions']),
                $this->types->importFromTypes(types: ['Failure', 'Result']),
                $this->utils->importFromUtils(values: ['isValidEnvelop']),
            ]),
            self::OPERATION_EXCEPTION_FILE => new TypescriptFile(<<<'TypeScript'
/**
 * Generic over the names the operation exposed, so `e.cause.details.name` narrows to those rather
 * than to any string. The rest of the catalogue is the server's and needs no naming here.
 */
export class OperationException<TDomainType extends string = string> extends Error {
    public readonly cause: Failure<TDomainType>;

    /**
     * No server answered this one — the request never left, or what came back was not the server's
     * envelope — so nothing on it came off the wire and `cause.cause` holds the exception that
     * stopped it. A method rather than a getter, because TypeScript allows a type predicate only on
     * a function: calling it narrows `cause` to the client branch.
     */
    public isClientError(): this is OperationException<TDomainType> & {cause: Failure<TDomainType> & ClientError} {
        return this.cause.code === 0;
    }

    get code(): number {
        return this.cause.code;
    }

    constructor(cause: Failure<TDomainType>) {
        super(`Operation failed with code ${cause.code}`);
        this.cause = cause;
    }

    public static is<TDomainType extends string = string>(e: unknown): e is OperationException<TDomainType> {
        return e instanceof OperationException;
    }
}
TypeScript, [
                $this->types->importFromTypes(types: ['ClientError', 'Failure']),
            ]),
            self::BINDINGS_FILE => new TypescriptFile(<<<TypeScript
let client: OperationClient|null;

export function createDefaultClient(
    fetcher?: typeof window.fetch,
    options?: {baseUrl?: string; timeoutMs?: number},
): DefaultClient {
    return new DefaultClient(fetcher ?? fetch, {
        paths: {query: '{$metadata->queryUrl}', command: '{$metadata->commandUrl}'},
        baseUrl: options?.baseUrl ?? '',
        timeoutMs: options?.timeoutMs ?? 10000,
    });
}

export function setClient(operationClient: OperationClient|null): void {
    client = operationClient;
}

export async function executeOperation<I, O, TDomainType extends string = never>(type: 'query'|'command', key: string, input: I, options?: OperationOptions & {client?: OperationClient}): Promise<Result<O, TDomainType>> {
    if (options?.client) {
        return await options.client.execute(type, key, input, options);
    }

    if (client) {
        return await client.execute(type, key, input, options);
    }

    throw new Error('No client set');
}
TypeScript, [
                $this->types->importFromTypes(types: ['Result']),
                $this->importFromOperationClient(types: ['OperationClient', 'OperationOptions']),
                // Constructed, not just annotated: a type only import would leave
                // `new DefaultClient(...)` referencing nothing at runtime.
                $this->importFromDefaultClient(values: ['DefaultClient']),
            ]),
        ];
    }
}
