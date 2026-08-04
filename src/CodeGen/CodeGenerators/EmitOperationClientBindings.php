<?php declare(strict_types=1);

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
 * Not readonly: the EmitTypes its files import from is injected after construction, which is the
 * only way it can be the same instance the generator runs.
 */
final class EmitOperationClientBindings implements GeneratesLibFiles, DependsOn
{
    private const string BINDINGS_FILE = "bindings";
    private const string OPERATION_CLIENT_FILE = "OperationClient";
    private const string DEFAULT_CLIENT_FILE = "DefaultClient";
    private const string OPERATION_EXCEPTION_FILE = "OperationException";

    private EmitTypes $types;

    /**
     * One method per file this generator writes, so nothing outside spells a file name it does not
     * own. Not static: reaching them means declaring the dependency, and a declared dependency that
     * is not registered fails the run before a line is generated.
     *
     * @param list<string> $values
     * @param list<string> $types
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
     * @param list<string> $values
     * @param list<string> $types
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
     * @param list<string> $values
     * @param list<string> $types
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
     * @param list<string> $values
     * @param list<string> $types
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
        ];
    }

    #[Override]
    public function setDependencies(array $dependencies): void
    {
        $this->types = Assertions::instanceOf(
            EmitTypes::class,
            $dependencies[EmitTypes::class] ?? null,
        );
    }

    /**
     * @return array<string, TypescriptFile>
     */
    #[Override]
    public function emitFiles(array $operations, ServerMetadata $metadata, AliasRegistry $registry): array
    {
        return [
            self::OPERATION_CLIENT_FILE => new TypescriptFile(<<<TypeScript
export type OperationOptions = {signal?: AbortSignal; timeoutMs?: number; client?: OperationClient};

export interface OperationClient {
    execute<O, E extends {code: number}>(
        type: "command"|"query", 
        key: string, 
        input: unknown, 
        options?: OperationOptions
    ): Promise<WithClientDirectives<Result<O, E>>>;
}
TypeScript, [
                $this->types->importFromTypes(types: ['Result', 'WithClientDirectives']),
            ]),
            self::DEFAULT_CLIENT_FILE => new TypescriptFile(<<<TypeScript
export type Hook = (result: WithClientDirectives<Result<unknown, {code: number}>>) => Promise<void> | void;

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
                return `\${encodeURIComponent(key)}=\${encodeURIComponent(JSON.stringify(value))}`;
            }).join('&');
    }

    private async callHooks<const T extends Result<unknown, {code: number}>>(result: WithClientDirectives<T>) {
        try {
            await Promise.all(this.hooks.map(hook => hook(result)));
            return result;
        } catch (error) {
            console.error('Error while calling hooks', error);
            return result;
        }
    }

    async execute<O, E extends {code: number}>(type: "command" | "query", key: string, input: unknown, options?: OperationOptions): Promise<WithClientDirectives<Result<O, E>>> {
        const route = this.options.paths[type].substring(0, 1) === '/' ? this.options.paths[type].substring(1) : this.options.paths[type];
        const fullPath = `\${this.options.baseUrl ?? ''}/\${route.replace('{fqn}', key)}`;

        const timeoutInMs = this.options?.timeoutMs ?? options?.timeoutMs;
        const signal = this.joinSignals([
            options?.signal,
            timeoutInMs ? new AbortController().signal : undefined
        ]);

        const headers: Record<string, string> = {
            Accept: 'application/json',
            "X-Client-ID": "operations-spa"
        };

        if (type === 'command') {
            headers['Content-Type'] = 'application/json';
        }

        const queryParams = type === 'query' && input && typeof input === 'object'
            ? `?\${this.createJsonEncodedQueryParams(input)}`
            : '';

        const response = await this.fetcher(`\${fullPath}\${queryParams}`, {
            method: type === 'query' ? 'GET' : 'POST',
            signal,
            headers,
            body: type === 'command' ? JSON.stringify(input) : undefined,
        });

        const json = await response.json();
        if (!json || typeof json !== 'object') {
            throw new Error('Invalid response body. Could not parse json correctly.');
        }

        if (response.ok) {
            return await this.callHooks({...json, success: true} as WithClientDirectives<Success<O>>);
        }

        return await this.callHooks({
            ...json,
            success: false,
            code: json?.code ?? response.status,
            type: json?.type ?? 'INTERNAL_ERROR'
        } as WithClientDirectives<Failure<E>>);
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
                $this->types->importFromTypes(
                    types: ['Failure', 'Result', 'Success', 'WithClientDirectives'],
                ),
            ]),
            self::OPERATION_EXCEPTION_FILE => new TypescriptFile(<<<TypeScript
export class OperationException extends Error {
    public readonly cause: Failure<any>;

    get code(): number {
        const code = this.cause.code;
        if (!code || typeof code !== 'number' || Number.isNaN(code)) {
            return 500;
        }
        
        return code;
    }

    constructor(cause: Failure<any>) {
        super(`Operation failed with code \${cause.code}`);
        this.cause = cause;
    }
    
    public static is(e: unknown): e is OperationException {
        return e instanceof OperationException;
    }
}
TypeScript, [
                $this->types->importFromTypes(types: ['Failure']),
            ]),
            self::BINDINGS_FILE => new TypescriptFile(<<<TypeScript
let client: OperationClient|null;

export function createDefaultClient(fetcher?: typeof window.fetch): DefaultClient {
    return new DefaultClient(fetcher ?? fetch, {
        paths: {query: '{$metadata->queryUrl}', command: '{$metadata->commandUrl}'},
        baseUrl: '',
        timeoutMs: 10000,
    });
}

export function setClient(operationClient: OperationClient|null): void {
    client = operationClient;
}

export function throwOnFailure<const T>(result: Result<T, any>): asserts result is Success<T> {
    if (!result.success) {
        throw new OperationException(result);
    }
}

export async function executeOperation<I, O, E extends {code: number}>(type: 'query'|'command', key: string, input: I, options?: OperationOptions & {client?: OperationClient}): Promise<WithClientDirectives<Result<O, E>>> {
    if (options?.client) {
        return await options.client.execute(type, key, input, options);
    }

    if (client) {
        return await client.execute(type, key, input, options);
    }

    throw new Error('No client set');
}
TypeScript, [
                $this->types->importFromTypes(types: ['Result', 'Success', 'WithClientDirectives']),
                $this->importFromOperationClient(types: ['OperationClient', 'OperationOptions']),
                // Both are constructed, not just annotated: a type only import would leave
                // `new DefaultClient(...)` referencing nothing at runtime.
                $this->importFromDefaultClient(values: ['DefaultClient']),
                $this->importFromOperationException(values: ['OperationException']),
            ]),
        ];
    }
}