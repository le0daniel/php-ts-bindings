import {Result, WithClientDirectives, OperationOptions, FullyQualifiedName} from "./types";

export interface OperationClient {
    execute<O, E extends object>(type: "command"|"query", fullyQualifiedName: FullyQualifiedName, input: unknown, options?: OperationOptions): Promise<WithClientDirectives<Result<O, E>>>;
}