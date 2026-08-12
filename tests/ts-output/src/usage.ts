/**
 * Hand written, on purpose. Typechecking `generated/` alone only proves the generated files agree
 * with each other — types that are well formed but unusable would pass. This is a consumer of the
 * client the way an application writes one, so the compiler has to accept the calls too.
 *
 * Nothing here runs. It exists to be typechecked by `composer codegen:fixture`.
 */
import {find, lock} from '../generated/accounts';
import type {ProductDomainErrors} from '../generated/catalog';
import {prepare, product, productQueryKey, productQueryOptions, restock, search, useProductQuery} from '../generated/catalog';
import {createDefaultClient, setClient} from '../generated/lib/bindings';
import type {OperationsClientPayload} from '../generated/lib/client-operations-spa';
import {containsOperationSpaPayload} from '../generated/lib/client-operations-spa';
import {OperationException} from '../generated/lib/OperationException';
import type {Brand, ClientError, Failure, InternalError, Product} from '../generated/lib/types';
import type {TypeMap} from '../generated/lib/type-map';
import {isValidEnvelop, throwOnFailure} from '../generated/lib/utils';
import {defaults, submit, useDefaultsQuery} from '../generated/shapes';

setClient(createDefaultClient(fetch));

// A brand is opaque: a plain number is not a ProductId, so one has to be minted deliberately at the
// boundary. That is the whole point of the emitted Brand helper.
const productId = 12 as number & Brand<'productId'>;
const sku = 'ABC-1' as string & Brand<'sku'>;

/**
 * The result is a discriminated union: `success` picks the branch, `code` picks the failure.
 */
export async function readProduct(): Promise<Product | null> {
    const result = await product({id: productId});

    if (result.success) {
        return result.data;
    }

    switch (result.code) {
        case 422:
            console.warn('invalid input', result.details.fields);
            return null;
        case 429: {
            // The one branch that says when to come back. `details` is always declared - the
            // server could not know a retryIn is a null value, never a missing key.
            const retryIn: number | null = result.details.retryIn;
            console.warn('rate limited', retryIn);
            return null;
        }
        case 0:
            // The one branch no server sent. The request never got there, so what went wrong is the
            // exception itself rather than anything that came off the wire, and it arrives intact.
            console.error('request failed', result.cause.message);
            return null;
        case 401:
        case 403:
        case 404:
        case 500:
            // `details` only exists where the category cannot say everything on its own. Here it
            // can, so the server omits the key and the branch has no such property. The directive
            // below is the guard: putting one back makes the access legal and fails this build.
            // @ts-expect-error
            console.debug(result.details);
            return null;
    }
}

/**
 * The catalogue is the whole server's, but a 400 is not: `catalog.product` exposes no exception, so
 * its Failure is instantiated with `never` and the branch is gone rather than present-but-empty.
 * The comparison below has nothing to overlap with, which is the guarantee — an operation cannot be
 * asked about a failure it can never produce.
 */
export async function productHasNoDomainError(): Promise<void> {
    const result = await product({id: productId});
    if (result.success) {
        return;
    }

    // @ts-expect-error
    if (result.code === 400) {
        console.debug(result);
    }
}

/**
 * Every branch is a named type declared once, so a handler can be written against the ones it cares
 * about and reused across operations — rather than each call site restating the literal shape.
 */
function isWorthRetrying(error: ClientError | InternalError): boolean {
    // A cancelled request is not a failure worth repeating; anything else that never reached the
    // server is. Narrowing the parameter on `code` reaches `cause`, which only one branch has.
    return error.code === 500 || !(error.cause instanceof DOMException);
}

export async function readProductOrRetryLater(): Promise<Product | 'retry' | null> {
    const result = await product({id: productId});
    if (result.success) {
        return result.data;
    }

    // The compiler checks that these two branches are the ones the helper accepts, instead of
    // taking the call site's word for it.
    if (result.code === 0 || result.code === 500) {
        return isWorthRetrying(result) ? 'retry' : null;
    }

    return null;
}

/**
 * throwOnFailure narrows the same union by asserting, for code that would rather catch than branch.
 */
export async function readProductOrThrow(): Promise<Product> {
    try {
        const result = await product({id: productId});
        throwOnFailure(result);
        return result.data;
    } catch (error) {
        // A catch clause variable is `unknown` whatever was thrown, so what the operation exposed is
        // named here rather than inferred. OperationException is generic over exactly that — the rest
        // of the catalogue is the server's and needs no naming.
        if (OperationException.is<ProductDomainErrors>(error)) {
            // No <Name>Error is generated: the envelope is Failure with the operation's names in it,
            // which is the whole of what an alias for it would have said.
            const failureType: Failure<ProductDomainErrors>['type'] = error.cause.type;
            console.error('operation failed', error.code, failureType);

            if (error.cause.code === 422) {
                const fields: Record<string, string[]> = error.cause.details.fields;
                console.error('invalid input', fields);
            }
        }
        throw error;
    }
}

/**
 * Optional input keys stay optional, and a named enum arrives as its case union.
 */
export async function searchProducts(): Promise<Product[]> {
    const withoutOptionals = await search({term: 'lamp'});
    const withOptionals = await search({term: 'lamp', availability: 'IN_STOCK', limit: 10});

    if (!withoutOptionals.success || !withOptionals.success) {
        return [];
    }

    return [...withoutOptionals.data.results, ...withOptionals.data.results];
}

/**
 * A class with one shape per direction: built from a title, read back as a slug.
 */
export async function prepareDraft(): Promise<string | null> {
    const result = await prepare({title: 'Summer sale'});
    return result.success ? result.data.slug : null;
}

/**
 * A query that takes no input at all — the generated signature has no first argument.
 */
export async function readDefaults(): Promise<string> {
    const result = await defaults();
    if (!result.success) {
        return '';
    }

    // Literals stay literal, unions stay unions, mixed arrives as unknown.
    const answer: 42 = result.data.answer;
    const either: string | number = result.data.either;
    const anything: unknown = result.data.anything;
    const pair: [string, number] = result.data.pair;
    const lookup: Record<string, number> = result.data.lookup;

    console.debug(answer, either, anything, pair, lookup);
    return result.data.nested.deep.value;
}

/**
 * Commands go over POST and can carry client directives back. The envelope names `__client` but
 * declares it `unknown` — the key is the library's, the schema is whichever Client emitted it — so
 * one guard from that client is what puts it on the result, fully typed, for the rest of the
 * function.
 */
export async function lockAccount(id: number): Promise<OperationsClientPayload | null> {
    const result = await lock({id});

    if (!result.success && result.code === 400) {
        // Two exposed exceptions become a union the client discriminates on.
        const name: 'account_locked' | 'quota_exceeded' = result.details.name;
        console.warn(name);
        return null;
    }

    if (!containsOperationSpaPayload(result)) {
        return null;
    }

    // No second round of guards: past the check every directive has its declared type.
    for (const toast of result.__client.toasts ?? []) {
        console.info(toast.type, toast.message);
    }

    if (result.__client.redirect) {
        window.location.href = result.__client.redirect.url;
    }

    for (const [namespace, ...key] of result.__client.invalidations ?? []) {
        console.debug('invalidate', namespace, key);
    }

    return result.__client;
}

/**
 * A command whose input nests a branded id and a date that travels as a string.
 */
export async function submitPayload(): Promise<boolean> {
    const result = await submit({payload: {id: productId, when: '2026-08-04'}, dryRun: true});
    return result.success && result.data.accepted;
}

export async function restockProduct(): Promise<void> {
    await restock({sku, amount: 5, price: {amount: 1290, currency: 'CHF'}});
}

/**
 * Cancellation and a custom timeout travel through OperationOptions.
 */
export async function findAccount(signal: AbortSignal): Promise<void> {
    await find({term: 'leo'}, {signal, timeoutMs: 2500});
}

/* The tanstack bindings: a key, options, and the hook built on top of them. */

export const cacheKey = productQueryKey({id: productId});

export const cacheOptions = productQueryOptions({id: productId}, {
    staleTime: 30_000,
    retry: false,
});

export function useProduct() {
    const query = useProductQuery({id: productId}, {enabled: true});
    const defaultsQuery = useDefaultsQuery();

    return {product: query.data, defaults: defaultsQuery.data};
}

/* The type map is the whole server as one type, addressable by operation key. */

export type ProductInputFromMap = TypeMap['query']['catalog.product']['input'];
export type ProductOutputFromMap = TypeMap['query']['catalog.product']['output'];
export type LockErrorsFromMap = TypeMap['command']['accounts.lock']['errors'];

/**
 * `__metadata` is declared on both branches, so a middleware's bag is readable without narrowing
 * first and without a guard — unlike `__client`, whose schema belongs to whichever Client is
 * plugged in. Optional, because the server leaves the key off when nothing was attached.
 */
export async function readMetadata(): Promise<unknown> {
    const result = await product({id: productId});

    // Before the union is narrowed: both branches agree it may be there.
    const durationMs: unknown = result.__metadata?.durationMs;

    if (!result.success) {
        // Still there on the failure branch, alongside the error's own keys.
        console.debug(result.code, result.__metadata);
        return durationMs;
    }

    // Values are `unknown`: the bag is the application's to shape, so the envelope refuses to
    // guess. Reading one as a string has to be a deliberate assertion.
    // @ts-expect-error
    const handler: string = result.__metadata?.fullyQualifiedHandler;
    console.debug(handler, result.data.sku);

    return durationMs;
}

/**
 * `__client` is declared, but only as `unknown`, and only on the success branch. Both halves of that
 * are load bearing, so both are pinned here.
 */
export async function clientChannelIsNamedButNotDescribed(id: number): Promise<void> {
    const result = await lock({id});

    if (!result.success) {
        // A failure carries no directives at all — RpcError holds no Client, so a toast queued
        // before the throw never reaches the browser. The branch has no such property to read.
        // @ts-expect-error
        console.debug(result.__client);
        return;
    }

    // Present on success, and `unknown`: reading a directive off it without narrowing first is
    // exactly the claim the envelope refuses to make.
    // @ts-expect-error
    console.debug(result.__client?.toasts);

    // The guard is the way through, and it is the shipped client's, not the envelope's.
    if (containsOperationSpaPayload(result)) {
        console.debug(result.__client.type satisfies 'operations-spa');
    }
}

/**
 * A failed HTTP status is not blindly a server failure: a body that is not the server's envelope
 * arrives as the client branch, carrying the raw response for whoever wants to look.
 */
export async function inspectRawResponse(): Promise<void> {
    const result = await product({id: productId});
    if (result.success) {
        return;
    }

    if (result.code === 0) {
        // Only the client branch carries it, and it is optional: a request that never left has no
        // response at all, and a non-JSON body has no jsonResponse.
        const status: number | undefined = result.response?.httpStatusCode;
        const body: unknown = result.response?.jsonResponse;
        console.warn('not a server answer', status, body, result.cause.message);
        return;
    }

    // A real server failure has nothing raw to show — the envelope is the answer.
    // @ts-expect-error
    console.debug(result.response);
}

/**
 * The guard the transport itself trusts is exported, so a payload from anywhere else — SSR state,
 * a cache — can be believed (or not) the same way, and past it the value is the envelope.
 */
export function readEmbeddedEnvelope(raw: unknown): unknown {
    if (!isValidEnvelop(raw)) {
        return null;
    }

    return raw.success ? raw.data : raw.code;
}

/**
 * isClientError is a method and a type guard: past it, `cause` *is* the client branch — which a
 * getter could never say, because TypeScript allows a predicate only on a function.
 */
export function reportFailure(error: OperationException<ProductDomainErrors>): string {
    // Before the guard the union still holds every branch, so the client-only keys are not there.
    // @ts-expect-error
    console.debug(error.cause.response);

    // The old getter shape is gone; an unmigrated call site reads a truthy function and fails to
    // compile rather than silently taking every failure for a client one.
    // @ts-expect-error
    if (error.isClientError) {
        console.debug('unreachable');
    }

    if (error.isClientError()) {
        const cause: Error = error.cause.cause;
        const status: number | undefined = error.cause.response?.httpStatusCode;
        error.cause.type satisfies 'CLIENT_ERROR';
        return `${cause.message} (${status ?? 'no response'})`;
    }

    return error.message;
}
