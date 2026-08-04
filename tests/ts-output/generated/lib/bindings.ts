import {DefaultClient} from './DefaultClient';
import type {OperationClient, OperationOptions} from './OperationClient';
import {OperationException} from './OperationException';
import type {Result, Success, WithClientDirectives} from './types';

let client: OperationClient|null;

export function createDefaultClient(fetcher?: typeof window.fetch): DefaultClient {
    return new DefaultClient(fetcher ?? fetch, {
        paths: {query: '/query/{fqn}', command: '/command/{fqn}'},
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
