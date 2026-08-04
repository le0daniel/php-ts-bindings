/**
 * Hand written, on purpose. Typechecking `generated/` alone only proves the generated files agree
 * with each other — types that are well formed but unusable would pass. This is a consumer of the
 * client the way an application writes one, so the compiler has to accept the calls too.
 *
 * Nothing here runs. It exists to be typechecked by `composer codegen:fixture`.
 */
import {find, lock} from '../generated/accounts';
import {prepare, product, productQueryKey, productQueryOptions, restock, search, useProductQuery} from '../generated/catalog';
import {createDefaultClient, setClient, throwOnFailure} from '../generated/lib/bindings';
import {OperationException} from '../generated/lib/OperationException';
import type {Brand, Product, SPAClientDirectives} from '../generated/lib/types';
import type {TypeMap} from '../generated/lib/type-map';
import {isClientRedirect, isClientToast, isSpaClientDirectives} from '../generated/lib/utils';
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
        case 404:
        case 500:
            return null;
    }
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
        if (OperationException.is(error)) {
            console.error('operation failed', error.code, error.cause.type);
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
 * Commands go over POST and can carry client directives back, which the emitted guards narrow.
 */
export async function lockAccount(id: number): Promise<SPAClientDirectives<unknown> | null> {
    const result = await lock({id});

    if (!result.success && result.code === 400) {
        // Two exposed exceptions become a union the client discriminates on.
        const type: 'account_locked' | 'quota_exceeded' = result.details.type;
        console.warn(type);
        return null;
    }

    if (!isSpaClientDirectives(result)) {
        return null;
    }

    for (const toast of result.__client.toasts ?? []) {
        if (isClientToast(toast)) {
            console.info(toast.type, toast.message);
        }
    }

    if (result.__client.redirect && isClientRedirect(result.__client.redirect)) {
        window.location.href = result.__client.redirect.url;
    }

    return result;
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
