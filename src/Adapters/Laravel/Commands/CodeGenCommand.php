<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use JsonException;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Helpers\CodeGen\OperationDescription;
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Contracts\ClientAwareException;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Operations\Data\Operation;
use Le0daniel\PhpTsBindings\Operations\JustInTimeDiscoveryRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CodeGenCommand extends Command
{
    private TypescriptDefinitionGenerator $typescriptGenerator;
    protected $signature = 'operations:codegen {directory}';
    protected $description = 'Generate the typescript bindings for all operations';

    /**
     * @throws \ReflectionException
     * @throws JsonException
     */
    public function handle(OperationRegistry $registry, Router $router): void
    {
        $this->typescriptGenerator = new TypescriptDefinitionGenerator();
        if (!$registry instanceof JustInTimeDiscoveryRegistry) {
            throw new RuntimeException('Cannot generate code for a registry that is not a JustInTimeDiscoveryRegistry');
        }

        $directory = str_starts_with('/', $this->argument('directory'))
            ? $this->argument('directory')
            : base_path($this->argument('directory'));

        $this->clearDirectory($directory);
        $this->generateOperationUtility($directory, array_keys($registry->getAllByNamespace()), $router);

        foreach ($registry->getAllByNamespace() as $namespace => $operations) {
            file_put_contents(
                "{$directory}/{$namespace}.ts",
                implode(PHP_EOL, [
                    $this->generateOperationImports(),
                    '',
                    ... array_map($this->generateOperationCode(...), $operations)
                ]) . PHP_EOL
            );
        }
    }

    private function generateOperationImports(): string
    {
        return <<<TypeScript
import type { OperationOptions, Result } from './bindings';
import { executeOperation, throwOnFailure } from './bindings';
import { useQuery } from '@tanstack/react-query';

TypeScript;
    }

    /**
     * @param string $directory
     * @param list<string> $namespaces
     * @return void
     * @throws JsonException
     */
    private function generateOperationUtility(string $directory, array $namespaces, Router $router): void
    {
        $queryRoute = $router->getRoutes()->getByName(LaravelHttpController::QUERY_NAME)->uri();
        $commandRoute = $router->getRoutes()->getByName(LaravelHttpController::COMMAND_NAME)->uri();

        $namespaceUnion = implode('|', array_map(fn(string $namespace) => json_encode($namespace, flags: JSON_THROW_ON_ERROR), $namespaces));

        file_put_contents("{$directory}/bindings.ts", <<<TypeScript
export type OperationNamespaces = {$namespaceUnion};
export type OperationOptions = {signal?: AbortSignal; timeoutMs?: number;};
export type Hook = (type: 'query'|'command', actionName: string, input: unknown, result: WithClientDirectives<Result<unknown, object>>) => Promise<void> | void;

const hooks: Hook[] = [];
let customFetcher: typeof window.fetch | null = null;
let operationOptions = {
    timeoutMs: 10000,
    paths: {
        query: '/{$queryRoute}',
        command: '/{$commandRoute}',
    }
};

type InternalError = {code: 500;type: "INTERNAL_ERROR"} 
type InvalidInputError = {code: 422;type: "INVALID_INPUT";data: Record<string, string[]>;}
type AuthenticationError = {code: 401;type: "UNAUTHENTICATED";}
type AuthorizationError = {code: 403;type: "UNAUTHORIZED";}

export type Success<T> = {success: true, data: T}
export type Failure<E extends object = never> = {success: false} & (InternalError | InvalidInputError | AuthenticationError | AuthorizationError | E)
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

export function isSpaClientDirectives<const T>(result: WithClientDirectives<T>): result is SPAClientDirectives<T> {
    if (!result.__client || typeof result.__client !== 'object') {
        return false;
    }
    
    return "type" in result.__client && result.__client.type === "operations-spa";
}

export function configureFetcher(fetcher: typeof window.fetch): void {
    customFetcher = fetcher;
}

export function configure(options: Partial<typeof operationOptions>): void {
    operationOptions = {...operationOptions, ...options};
}

export function addHook(hook: Hook): (() => void) {
    hooks.push(hook);
    return () => {
        const index = hooks.indexOf(hook);
        if (index >= 0) {
            hooks.splice(index, 1); 
        }
    };
}

export function throwOnFailure<const T>(result: Result<T>): asserts result is Success<T> {
    if (!result.success) {
        throw new Error('Operation failed');   
    }
}

function createJsonEncodedQueryParams(input: object): string {
    return Object.entries(input).map(([key, value]) => {
        return `\${encodeURIComponent(key)}=\${encodeURIComponent(JSON.stringify(value))}`;
    }).join('&');
}

async function callHooks<T, E extends object>(type: 'query'|'command', actionName: string, input: unknown, result: WithClientDirectives<Result<T, E>>): Promise<WithClientDirectives<Result<T, E>>> {
    try {
        await Promise.all(hooks.map(hook => hook(type, actionName, input, result)));
        return result;  
    } catch (error) {
        console.error('Error while calling hooks', error);
        return result;   
    }
}

export async function executeOperation<I, O, E extends object>(type: 'query'|'command', fqn: string, input: I, options?: OperationOptions): Promise<WithClientDirectives<Result<O, E>>> {
    const fetcher = customFetcher ?? fetch;
    const fullyQualifiedPath = operationOptions.paths[type].replace('{fqn}', fqn);

    const timeout = AbortSignal.timeout(options?.timeoutMs ?? operationOptions.timeoutMs);
    const signal = options?.signal ? AbortSignal.any([options?.signal, timeout]) : timeout;

    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (type === 'command') {
        headers['Content-Type'] = 'application/json';
    }

    const queryParams = type === 'query' && input && typeof input === 'object'
        ? `?\${createJsonEncodedQueryParams(input)}`
        : '';

    const response = await fetcher(`\${fullyQualifiedPath}\${queryParams}`, {
        method: type === 'query' ? 'GET': 'POST',
        signal,
        headers,
        body: type === 'command' ? JSON.stringify(input) : undefined,
    });

    // ToDo: deal with response
    const json = await response.json();
    if (!json || typeof json !== 'object') {
        throw new Error('Invalid response body. Could not parse json correctly.');
    }

    if (response.ok) {
        return await callHooks(type, fqn, input, {...json, success: true} as WithClientDirectives<Success<O>>);
    }

    console.error('Request failed', response, json);
    return await callHooks(type, fqn, input, {
        ...json,
        success: false,
        code: json?.code ?? response.status,
        type: response.type ?? 'INTERNAL_ERROR'
    } as WithClientDirectives<Failure<E>>);
}
 
TypeScript
        );
    }

    private function generateErrorTypes(Operation $operation): ?string
    {
        if (empty($operation->definition->caughtExceptions)) {
            return null;
        }

        return implode('|', array_map(
            function (string $clientAwareError): string {
                /** @var class-string<ClientAwareException> $clientAwareError */
                $code = $clientAwareError::code();
                $type = $clientAwareError::type();
                return "{code:{$code};type:'{$type}';}";
            },
            $operation->definition->caughtExceptions,
        ));
    }

    private function generateOperationCode(Operation $operation): string
    {
        $inputDefinition = $this->typescriptGenerator->toDefinition($operation->inputNode(), DefinitionTarget::INPUT);
        $outputDefinition = $this->typescriptGenerator->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT);
        $handledErrors = $this->generateErrorTypes($operation);
        $handledErrorsOrNever = $handledErrors ? "{$handledErrors}" : 'never';
        $resultType = $handledErrors ? "Result<{$outputDefinition},{$handledErrors}>" : "Result<{$outputDefinition}>";

        $description = OperationDescription::describe($operation);

        return <<<TypeScript
{$description}
export async function {$operation->definition->name}(input: {$inputDefinition}, options?: OperationOptions): Promise<{$resultType}> {
    return await executeOperation<{$inputDefinition},{$outputDefinition}, {$handledErrorsOrNever}>('{$operation->definition->type}', '{$operation->definition->fullyQualifiedName()}', input, options);
}
{$this->generateQueryFunctionAndKey($operation)}

TypeScript;
    }

    /**
     * @throws JsonException
     */
    private function generateQueryFunctionAndKey(Operation $operation): string
    {
        if ($operation->definition->type !== 'query') {
            return '';
        }

        $queryFnName = ucfirst($operation->definition->name);
        $namespace = json_encode($operation->definition->namespace, JSON_THROW_ON_ERROR);
        $name = json_encode($operation->definition->name, JSON_THROW_ON_ERROR);
        $inputDefinition = $this->typescriptGenerator->toDefinition($operation->inputNode(), DefinitionTarget::INPUT);
        $outputDefinition = $this->typescriptGenerator->toDefinition($operation->outputNode(), DefinitionTarget::OUTPUT);

        return <<<TypeScript
/**
 * This is a react query function for a query operation. Defined in:
 * @php {$operation->definition->fullyQualifiedClassName}::{$operation->definition->methodName}
 */
export function use{$queryFnName}Query(input: {$inputDefinition}, queryOptions?: Partial<{enabled: boolean}>) {
    return useQuery({
        queryKey: [/* namespace */ {$namespace}, /* name */ {$name}, input],
        queryFn: async ({signal}): Promise<{$outputDefinition}> => {
            const result = await {$operation->definition->name}(input, {signal});
            throwOnFailure(result);
            return result.data;
        },
        ... queryOptions,
    });
}
TypeScript;
    }

    private function clearDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() || !str_ends_with($file->getBasename(), '.ts')) {
                continue;
            }

            unlink($file->getRealPath());
        }
    }
}