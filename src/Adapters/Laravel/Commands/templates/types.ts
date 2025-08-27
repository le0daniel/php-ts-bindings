export type OperationNamespaces = 'NAMESPACE_UNION';
export type OPERATIONS = {query: {}, command: {}};
export type QUERY_MAP = {namespace: 'one'|'two'};
export type OperationOptions = {signal?: AbortSignal; timeoutMs?: number;};
export type FullyQualifiedName = `${string}.${string}`;

type InternalError = {code: 500;type: "INTERNAL_SERVER_ERROR"}
type InvalidInputError = {code: 422;type: "INVALID_INPUT";data: Record<string, string[]>;}
type AuthenticationError = {code: 401;type: "UNAUTHENTICATED";}
type AuthorizationError = {code: 403;type: "UNAUTHORIZED";}
type NotFoundError = {code: 404;type: "NOT_FOUND";}

export type Success<T> = {success: true, data: T}
export type Failure<E extends object = never> = {success: false} & (InternalError | InvalidInputError | AuthenticationError | AuthorizationError | NotFoundError | E)
export type Result<T, E extends object = never> = Success<T> | Failure<E>;
export type WithClientDirectives<T> = T & {__client?: unknown}
export type SPAClientDirectives<T> = T & {
    __client: {
        type: "operations-spa",
        redirect?: {type: "soft"|"hard"; url: string;},
        toasts?: {type: 'success'|'error'|'alert'|'info', message: string;}[],
        invalidations?: [string, string, ...unknown[]][]
    }
};

