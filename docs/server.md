# The server

The runtime half of the library: what runs an operation, how it is found, how it reaches HTTP, and
what to compile ahead of time before deploying. The short version lives in the
[README](../README.md); this is the full picture.

- [Server](#server)
- [Operation keys](#operation-keys)
- [Registries](#registries)
- [ServerAdapter](#serveradapter)
- [Results](#results)
- [Serving operations over HTTP](#serving-operations-over-http)
- [Preloading a query](#preloading-a-query)
- [Production](#production)
- [Extension points](#extension-points)

## Server

`Server` takes a registry of operations and runs one. Both methods are total — every `Throwable`,
including a failure resolving your handler, comes back as an `RpcError`:

```php
public function query(string $name, mixed $input, mixed $context, Client $client): RpcSuccess|RpcError
public function command(string $name, mixed $input, mixed $context, Client $client): RpcSuccess|RpcError
```

Its constructor is three arguments, two of which have working defaults:

```php
new Server(
    OperationRegistry $registry,
    ServerAdapter $adapter = new NewInstanceAdapter(),
    ServerConfiguration $configuration = new ServerConfiguration(),
)
```

The `SchemaExecutor` it runs schemas with is a public property, if you want to parse or serialize
something yourself with the same instance.

## Operation keys

**`$name` is the operation's *key*, not its plain name.** An `OperationKeyGenerator` turns
`namespace` + `name` into what the client calls.

| Generator | Produces |
|---|---|
| `PlainlyExposedKeyGenerator` | `"{$namespace}.{$name}"` — literal, readable keys. |
| `HashSha256KeyGenerator($pepper, $namespaceLength = 8, $fnNameLength = 24)` | Both parts hashed, so `users.get` is reachable only as an opaque key. |

The generated TypeScript always embeds whichever key the server produced, so this only matters when
you call the server by hand.

> **Pass one explicitly.** `eagerlyDiscover()` falls back to `HashSha256KeyGenerator` peppered with
> the string `'default'` — obfuscated keys, from a pepper anyone reading this page knows. The pepper
> is the first constructor argument and has no default of its own.

Obfuscation is not a security boundary: it keeps your operation names out of the shipped bundle, and
that is all. Two operations colliding on a truncated hash is an error at discovery, not a silent
overwrite — widen `fnNameLength` if a real application ever hits it.

Because keys are derived from the generator, changing it — including upgrading a version that changed
how keys are derived — invalidates both the [production cache](#production) and the generated client.
Recompile and regenerate together.

## Registries

`OperationRegistry` holds the operations: `has()`, `get()` and `all()`, keyed by operation type.

`EagerlyLoadedOperationRegistry` discovers them up front. Schemas are parsed **lazily**, per
operation, on first use — discovery reads attributes and signatures, not docblock types:

```php
EagerlyLoadedOperationRegistry::eagerlyDiscover($directoryOrDirectories, keyGenerator: $keys);
EagerlyLoadedOperationRegistry::withClasses([UserOperations::class, ...], keyGenerator: $keys);
```

`withClasses()` registers an explicit list instead of scanning, which is what you want when the
classes are already known — a compiled list, or a test.

Both take an `OperationDiscovery`, and `new OperationDiscovery($filterFn)` takes a
`Closure(ReflectionClass, ReflectionMethod, Query|Command): bool` returning `false` to keep an
operation out of the registry.

`CachedOperationRegistry` is the compiled form for production — see [Production](#production).

## ServerAdapter

`ServerAdapter` builds your handler classes and middleware. It is two methods —
`createController()` and `createMiddleware()` — and it is the seam for dependency injection:

| Adapter | Behaviour |
|---|---|
| `NewInstanceAdapter` | The default. Plain `new $className()`, so handlers take no constructor arguments. |
| `PsrContainerAdapter` | Resolves both through a PSR-11 container. |

`PsrContainerAdapter` needs `psr/container`, which is a `suggest` rather than a dependency; the
default needs nothing at all. Implement the interface yourself for a container that is not PSR-11,
or to construct handlers some other way. Whatever it does, a failure to resolve is caught and
returned as an `RpcError` — that is part of what keeps `query()` and `command()` total.

## Results

`RpcResult` is the interface both outcomes implement, for the code that does not care which one it
is holding. It carries `statusCode` — 200 on success, the [error category](errors.md)'s own code
otherwise — `resolveInfo`, `metadata`, and it is `JsonSerializable`: `jsonSerialize()` produces the
whole envelope the generated client reads. A transport needs nothing else, which is why
[serving operations](#serving-operations-over-http) is two lines.

Both results carry metadata a middleware can attach with `withMetadata()` / `appendMetadata()`. It
travels to the client under `__metadata`, and the generated envelope declares it as
`__metadata?: Record<string, unknown>` — optional because the key is left off entirely when nothing
was attached. It is yours to shape; the library puts nothing in it.

On the error branch, `$result->cause` is the most recent `Throwable`, ready to hand to your reporter,
and `$result->previous` is a list of everything that failed before it, oldest first. It is empty on
an ordinary error. On the rare occasion that working out how to present an error *itself* failed — a
stale middleware class name, say — that second exception is the `cause`, and the one your application
threw is in `previous`. `$result->throwableChain()` gives you all of them in order, which is what you
want to loop over when reporting:

```php
foreach ($result->throwableChain() as $throwable) {
    $reporter->report($throwable);
}
```

## Serving operations over HTTP

The server runs an operation; turning that into an HTTP response is yours to write. Two routes are
enough — one GET for queries, one POST for commands — and both must carry the operation key.

```php
use Le0daniel\PhpTsBindings\Server\Adapters\PsrContainerAdapter;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;

$server = new Server(
    EagerlyLoadedOperationRegistry::eagerlyDiscover(
        __DIR__ . '/src/Operations',
        keyGenerator: new PlainlyExposedKeyGenerator(),
    ),
    adapter: new PsrContainerAdapter($psrContainer),
    configuration: new ServerConfiguration()
        ->withMiddlewares(AuthMiddleware::class)
        ->withExceptions(
            notFound: [EntityNotFoundException::class],
            unauthenticated: [NotLoggedInException::class],
        ),
);

$result = $server->command('users.create', $input, $myContext, new NullClient());

// jsonSerialize() is the envelope the generated client reads, and the only thing that gets it
// exactly right: `details` is omitted rather than sent as null on the categories that have none,
// which is what the generated union declares.
respondJson($result->statusCode, $result->jsonSerialize());
```

Whatever your transport does with the input, the shape it hands the server has to match what the
generated client sends: for queries, each value JSON-encoded into its own query parameter; for
commands, a JSON body. Decoding query values back is what lets you leave
[`coerceQueryInput`](operations.md#serverconfiguration) off.

To emit [client directives](client-directives.md), pass an `OperationSPAClient` instead of a
`NullClient`. There is nothing else to do: the success carries the client, so `jsonSerialize()` asks
it for its payload and puts it under `__client` for you.

```php
$result = $server->command('users.create', $input, $myContext, new OperationSPAClient());

respondJson($result->statusCode, $result->jsonSerialize());
```

**Directives ride the success branch only.** A failure carries none, even for the directives that
were queued before it — a handler that toasts `'Saved'` and then throws must not have the browser
announce work that did not happen. That is why `RpcError` holds no client at all, rather than
leaving it to each transport to remember.

On Laravel, all of this is done for you — see [the Laravel adapter](laravel.md#routes).

## Preloading a query

`Preloader` runs a query server-side during the request that renders the page, so the data is in the
page instead of being fetched after it loads. It takes the `Server` and an `OperationKeyGenerator` —
which has to be the one the server's registry uses, or the key it derives names no operation and
every preload throws:

```php
use Le0daniel\PhpTsBindings\Server\Preloader;

$preloader = new Preloader($server, $keyGenerator);

$users = $preloader->preload('users', 'get', ['id' => 1], $context);
```

You get back `['response' => …, 'queryKey' => ['users', 'get', ['id' => 1]]]`. The key is built the
same way `EmitQueryKey` and `EmitTanstackQuery` build it, so a TanStack cache seeded with that pair
will not refetch. The input is part of the key whenever the operation has one, even when the value is
`null`; an operation whose input type *is* `null` gets a two-element key.

`preloadMany()` takes several at once, as
`[['namespace' => …, 'name' => …, 'input' => …], …]`. A query that fails throws a `SchemaException` —
this is your own code calling your own operation, not untrusted input.

## Production

Reflecting and parsing every schema on every request is real overhead. `CachedOperationRegistry` is
the compiled form of a registry: every schema pre-parsed, deduplicated and pooled — shared structs
emitted once and referenced, and unions reordered for faster dispatch. Compile it once, at deploy
time, and load it instead of discovering:

```php
CachedOperationRegistry::writeToCache($registry, __DIR__ . '/cache/operations.php', idLength: 10);
```

The output is deterministic, so the same registry compiles to the same bytes on every machine. A
cache that no longer matches the code asking it fails loudly, at runtime, with an
`UnknownTypeKeyException` — recompile it, or drop it and fall back to discovery.

> Operation keys are derived from the [key generator](#operation-keys), so changing it invalidates
> both the cache and the generated client. Recompile and regenerate together.

The same optimizer is available directly, for schemas that are not operations:

```php
use Le0daniel\PhpTsBindings\Parser\Helpers\ASTOptimizer;
use Le0daniel\PhpTsBindings\Parser\Helpers\Registry\CachedTypeRegistry;

new ASTOptimizer()->optimizeAndWriteToFile('asts.php', [
    'MyClass@method@input' => $inputAst,
    'MyClass@method@output' => $outputAst,
]);

/** @var CachedTypeRegistry $registry */
$registry = require 'asts.php';
$ast = $registry->get('MyClass@method@input');
```

Keys are interned as truncated hashes; `new ASTOptimizer(idLength: 12)` widens them if a run ever
reports a collision, which it does by throwing rather than by merging two schemas.

## Extension points

The interfaces meant to be implemented by you:

| Contract | For |
|---|---|
| `MiddlewareContract` | [Wrapping an operation](operations.md#middleware). |
| `ServerAdapter` | [Constructing handlers and middleware](#serveradapter) — the DI seam. |
| `OperationKeyGenerator` | [Turning `namespace` + `name`](#operation-keys) into the key the client calls. |
| `OperationRegistry` | [Holding operations](#registries), if neither shipped registry fits. |
| `Client` / `SerializableClient` | [Your own side channel](client-directives.md) and its wire payload. |
| `StringValueObject` / `IntValueObject` | [A class that travels as one primitive](types.md#value-objects). |
| `GeneratesLibFiles` / `GeneratesOperationCode` / `DependsOn` | [Adding to the generated client](typescript-client.md#writing-your-own-generator). |

`CodeGenerators::fromDefaults()` builds the default generator set for you, and returns a plain list
you can add yours to — see [the generators](typescript-client.md#generators).

The lower-level `TypeParser`, `SchemaExecutor` and `TypescriptGenerator` are public too, if you want
to parse and emit types without the RPC layer at all — see [the type reference](types.md).
