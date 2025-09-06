<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenerators;

use Le0daniel\PhpTsBindings\Adapters\Laravel\Data\GeneralMetadata;

final class EmitOperationClientBindings implements GeneratesLibFiles, DependsOn
{

    public function dependsOnGenerator(): array
    {
        return [
            EmitTypes::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function emitFiles(array $operations, GeneralMetadata $metadata): array
    {
        return [
            "OperationClient" => <<<TypeScript
import type {Result, WithClientDirectives} from "./types";

export type OperationOptions = {signal?: AbortSignal; timeoutMs?: number;};

export interface OperationClient {
    execute<O, E extends {code: number}>(
        type: "command"|"query", 
        key: string, 
        input: unknown, 
        options?: OperationOptions
    ): Promise<WithClientDirectives<Result<O, E>>>;
}
TypeScript,
            "DefaultClient" => <<<TypeScript
import type {OperationClient, OperationOptions} from "./OperationClient";
import type {Failure, Result, Success, WithClientDirectives} from "./types";

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

        console.error('Request failed', response, json);
        return await this.callHooks({
            ...json,
            success: false,
            code: json?.code ?? response.status,
            type: response.type ?? 'INTERNAL_ERROR'
        } as WithClientDirectives<Failure<E>>);
    }

    registerHook(hook: Hook): () => void {
        this.hooks.push(hook);
        return () => {
            this.hooks = this.hooks.filter(h => h !== hook);
        }
    }

}
TypeScript,
            "OperationException" => <<<TypeScript
import type {Failure} from "./types";

export class OperationException extends Error {
    public readonly cause: Failure<any>;

    constructor(cause: Failure<any>) {
        this.cause = cause;
        super(`Operation failed with code \${cause.code}`);
    }
    
    public static is(e: unknown): e is OperationException {
        return e instanceof OperationException;
    }
}
TypeScript,
            "bindings" => <<<TypeScript
import type { Result, Success, WithClientDirectives } from './types';
import type { OperationClient, OperationOptions } from './OperationClient';
import { DefaultClient } from './DefaultClient';
import { OperationException } from './OperationException';

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
TypeScript,
        ];
    }
}