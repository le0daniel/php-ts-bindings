import type { OperationOptions, Result, Success, WithClientDirectives, FullyQualifiedName } from './types';
import type { OperationClient } from './OperationClient';
import { DefaultClient } from './DefaultClient';

let client: OperationClient|null;

export function createDefaultClient(fetcher?: typeof window.fetch): DefaultClient {
    return new DefaultClient(window.fetch, {
        paths: {query: '{queryRoute}', command: '{commandRoute}'},
        baseUrl: '',
        timeoutMs: 10000,
    });
}

export function setClient(operationClient: OperationClient|null): void {
    client = operationClient;
}

export function throwOnFailure<const T>(result: Result<T>): asserts result is Success<T> {
    if (!result.success) {
        throw new Error('Operation failed');
    }
}

export async function executeOperation<I, O, E extends object>(type: 'query'|'command', fqn: FullyQualifiedName, input: I, options?: OperationOptions & {client?: OperationClient}): Promise<WithClientDirectives<Result<O, E>>> {
    if (options?.client) {
        return await options.client.execute(type, fqn, input, options);
    }

    if (!client) {
        throw new Error('No client set');
    }
    return await client.execute(type, fqn, input, options);
}