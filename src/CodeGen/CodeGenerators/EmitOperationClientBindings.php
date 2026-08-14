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
 * Moves a request and resolves to what came back: the status line and the body as parsed JSON, with
 * no claim about either. Everything that interprets a response — the envelope guard, the client
 * branch, the hooks — lives in executeOperation, once, whatever transport is plugged in. An
 * implementation only moves bytes, and it is allowed to throw (a network failure, an abort):
 * executeOperation turns that into the client branch too.
 */
export interface OperationClient {
    execute(
        type: "command"|"query",
        key: string,
        input: unknown,
        options?: OperationOptions
    ): Promise<{status: number; jsonBody: unknown}>;
}
TypeScript),
            self::DEFAULT_CLIENT_FILE => new TypescriptFile(<<<'TypeScript'
export class DefaultClient implements OperationClient {

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

    /**
     * One honest fetch: no guard, no catch, no observation. Whatever it throws — an abort, a
     * network failure, a bad timeout — is executeOperation's to catch, and whatever comes back is
     * handed over exactly as received, the status riding along unconsulted next to the parsed body.
     */
    async execute(type: "command" | "query", key: string, input: unknown, options?: OperationOptions): Promise<{status: number; jsonBody: unknown}> {
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

        const queryParams = type === 'query' && input && typeof input === 'object'
            ? `?${this.createJsonEncodedQueryParams(input)}`
            : '';

        const response = await this.fetcher(`${fullPath}${queryParams}`, {
            method: type === 'query' ? 'GET' : 'POST',
            signal,
            headers,
            body: type === 'command' ? JSON.stringify(input) : undefined,
        });

        const jsonBody: unknown = await response.json();
        return {status: response.status, jsonBody};
    }

}
TypeScript, [
                $this->importFromOperationClient(types: ['OperationClient', 'OperationOptions']),
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
let client: OperationClient|null = null;

/**
 * A hook sees the envelope of every operation, whichever client — the module global or a per call
 * options.client — served it, so it is typed against the widest domain union rather than any one
 * operation's. Every category is still there to discriminate on. The second argument names the
 * operation the envelope belongs to.
 */
export type Hook = (result: Result<unknown, string>, operation: {type: 'query'|'command'; key: string}) => Promise<void> | void;

let hooks: Hook[] = [];

export function registerHook(hook: Hook): () => void {
    hooks.push(hook);
    return () => {
        hooks = hooks.filter(h => h !== hook);
    };
}

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

/**
 * A hook that throws never fails the operation: the envelope is the answer, and observing it must
 * not change it.
 */
async function callHooks<const T extends Result<unknown, string>>(result: T, operation: {type: 'query'|'command'; key: string}): Promise<T> {
    try {
        await Promise.all(hooks.map(hook => hook(result, operation)));
    } catch (error) {
        console.error('Error while calling hooks', error);
    }

    return result;
}

/**
 * No type argument: this branch is in every Failure, whatever the operation exposed.
 */
function mintClientError(error: Error, response?: {httpStatusCode: number; jsonResponse?: unknown}): Failure {
    return response === undefined
        ? {success: false, code: 0, type: 'CLIENT_ERROR', cause: error}
        : {success: false, code: 0, type: 'CLIENT_ERROR', cause: error, response};
}

/**
 * Resolves, never rejects: every outcome — a valid envelope, a body that is not the envelope, a
 * transport that threw, no client at all — comes back as an envelope, and the hooks see every one
 * of them before the caller does.
 *
 * The status line is never consulted: anything between the browser and the handler can write one,
 * so only a body that is the server's own envelope counts as the server's answer, and a valid one
 * is returned exactly as parsed — whatever the server put next to it rides along untouched.
 * Whatever a transport threw is carried as itself rather than summarised: an AbortError has to stay
 * the DOMException it was for the code that rethrows exactly that one.
 */
export async function executeOperation<I, O, TDomainType extends string = never>(type: 'query'|'command', key: string, input: I, options?: OperationOptions): Promise<Result<O, TDomainType>> {
    const operation = {type, key};
    const activeClient = options?.client ?? client;

    if (!activeClient) {
        return await callHooks(mintClientError(new Error('No client set')), operation);
    }

    try {
        const {status, jsonBody} = await activeClient.execute(type, key, input, options);
        if (isValidEnvelop(jsonBody)) {
            // Narrowed only to the widest envelope: which data rides on success is the operation's
            // claim, asserted here once for every call site.
            return await callHooks(jsonBody as Result<O, TDomainType>, operation);
        }

        return await callHooks(mintClientError(
            new Error(`Invalid response envelope (HTTP status \${status})`),
            jsonBody === undefined ? {httpStatusCode: status} : {httpStatusCode: status, jsonResponse: jsonBody},
        ), operation);
    } catch (e: unknown) {
        const cause = e instanceof Error ? e : new Error(String(e));
        return await callHooks(mintClientError(cause), operation);
    }
}
TypeScript, [
                $this->types->importFromTypes(types: ['Failure', 'Result']),
                $this->importFromOperationClient(types: ['OperationClient', 'OperationOptions']),
                // Constructed, not just annotated: a type only import would leave
                // `new DefaultClient(...)` referencing nothing at runtime.
                $this->importFromDefaultClient(values: ['DefaultClient']),
                // The guard every body gates through, whatever transport produced it.
                $this->utils->importFromUtils(values: ['isValidEnvelop']),
            ]),
        ];
    }
}
