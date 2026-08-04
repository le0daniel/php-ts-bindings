import type {Failure} from './types';

export class OperationException extends Error {
    public readonly cause: Failure<any>;

    get code(): number {
        const code = this.cause.code;
        if (!code || typeof code !== 'number' || Number.isNaN(code)) {
            return 500;
        }
        
        return code;
    }

    constructor(cause: Failure<any>) {
        super(`Operation failed with code ${cause.code}`);
        this.cause = cause;
    }
    
    public static is(e: unknown): e is OperationException {
        return e instanceof OperationException;
    }
}
