import type {QUERY_MAP, SPAClientDirectives, WithClientDirectives} from "./types";

export function queryKey<const T extends keyof QUERY_MAP>(fqn: `${T}` | `${T}.${QUERY_MAP[T]}`, ...args: unknown[]): [string] | [string, string, ...unknown[]] {
    const parts = fqn.split('.', 2);
    return parts.length === 1 ? [parts[0]] : [parts[0], parts[1], ...args];
}

export function isSpaClientDirectives<const T>(result: WithClientDirectives<T>): result is SPAClientDirectives<T> {
    if (!result.__client || typeof result.__client !== 'object') {
        return false;
    }

    return "type" in result.__client && result.__client.type === "operations-spa";
}