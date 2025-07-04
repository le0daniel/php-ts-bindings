import {OperationClient} from "./OperationClient";
import {Failure, FullyQualifiedName, OperationOptions, Result, Success, WithClientDirectives} from "./types";

export type Hook = (result: WithClientDirectives<Result<unknown, object>>) => Promise<void> | void;


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

        return Object.entries(input).map(([key, value]) => {
            return `${encodeURIComponent(key)}=${encodeURIComponent(JSON.stringify(value))}`;
        }).join('&');
    }

    private async callHooks<const T extends Result<unknown, object>>(result: WithClientDirectives<T>) {
        try {
            await Promise.all(this.hooks.map(hook => hook(result)));
            return result;
        } catch (error) {
            console.error('Error while calling hooks', error);
            return result;
        }
    }

    async execute<O, E extends object>(type: "command" | "query", fullyQualifiedName: FullyQualifiedName, input: unknown, options?: OperationOptions): Promise<WithClientDirectives<Result<O, E>>> {
        const route = this.options.paths[type].substring(0, 1) === '/' ? this.options.paths[type].substring(1) : this.options.paths[type];
        const fullPath = `${this.options.baseUrl ?? ''}/${route}/${fullyQualifiedName}`;

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
            ? `?${this.createJsonEncodedQueryParams(input)}`
            : '';

        const response = await this.fetcher(`${fullPath}${queryParams}`, {
            method: type === 'query' ? 'GET': 'POST',
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