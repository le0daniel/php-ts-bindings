import {Result, WithClientDirectives, OperationOptions, FullyQualifiedName} from "./types";

export type Hook = (type: 'query'|'command', actionName: FullyQualifiedName, input: unknown, result: WithClientDirectives<Result<unknown, object>>) => Promise<void> | void;

export interface OperationClient {
    registerHook(hook: Hook): (() => void);
    execute<O, E extends object>(type: "command"|"query", fullyQualifiedName: FullyQualifiedName, input: unknown, options?: OperationOptions): Promise<WithClientDirectives<Result<O, E>>>;
}