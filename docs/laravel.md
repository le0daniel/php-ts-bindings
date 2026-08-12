# The Laravel adapter

A first-party adapter for [php-ts-bindings](../README.md). It is optional: the library requires
nothing but PHP 8.5, and everything Laravel-aware lives under `src/Adapters/Laravel/`.

This document covers what the adapter *adds* and what it *decides on your behalf*. For what an
operation is see [operations](operations.md), for what the error categories mean see
[errors](errors.md), and for what the generated client looks like see
[the TypeScript client](typescript-client.md).

- [Setup](#setup)
- [Configuration](#configuration)
- [Routes](#routes)
- [Context](#context)
- [Client](#client)
- [What the adapter decides for you](#what-the-adapter-decides-for-you)
- [Artisan commands](#artisan-commands)
- [Production](#production)
- [Preloading](#preloading)

## Setup

**1. The provider is auto-discovered.** `LaravelServiceProvider` is listed under
`extra.laravel.providers`, so there is nothing to register.

**2. Publish the config.**

```bash
php artisan vendor:publish --provider="Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider"
```

The publish group carries no tag, so `--tag=config` does not find it; name the provider.

Publishing is optional — the package config is merged in either way, and every key below has a
working default.

**3. Register the routes.** Nothing is registered for you — see [Routes](#routes).

**4. Write an operation** in `app/Operations`, as in the
[quickstart](../README.md#quickstart).

**5. Generate the client.**

```bash
php artisan operations:codegen resources/js/operations
```

## Configuration

`config/operations.php`:

| Key | Default | Purpose |
|---|---|---|
| `discovery_path` | `app_path('Operations')` | Where operations are discovered. A string or a list of them. |
| `context` | `null` | A `ContextFactory` class, building the `$context` every handler receives from the request. |
| `client` | `null` | A `ClientFactory` class, building the `Client` every handler receives from the request. `null` uses `OperationClientFactory`. |
| `key.mode` | `obfuscate` | `obfuscate`, `plain`, or `custom` with `key.className`. |
| `key.pepper` | `"none"` | Salt for `obfuscate` — the literal string `none`, not "no pepper". |
| `key.className` | `null` | An `OperationKeyGenerator`, required for `custom` and ignored otherwise. |
| `middleware` | `[]` | Global `MiddlewareContract` classes, run on every operation. |
| `exceptions.unauthenticated` | `AuthenticationException` | Mapped to 401. |
| `exceptions.unauthorized` | `TokenMismatchException`, `AuthorizationException` | Mapped to 403. |
| `exceptions.not_found` | `ModelNotFoundException`, `RecordNotFoundException`, `RecordsNotFoundException` | Mapped to 404. |
| `exceptions.rate_limited` | `[]` | Mapped to 429. Only matters for throttling inside a handler — Laravel's route-level throttle middleware answers before the operation runs. |
| `retry_in_resolver` | `null` | A `RetryInResolver` class name resolving `details.retryIn` (seconds) for 429s. When it returns a number, the HTTP controller also sets a standard `Retry-After` header. |
| `cache.idLength` | `10` | Id length used by the production cache. |

Exception matching is `instanceof`, so listing a base class covers its subclasses. An unrecognised
`key.mode` is an error rather than a fallback, because silently picking a different one would change
every key in the application.

> **The config is merged shallowly.** `mergeConfigFrom()` is a top-level `array_merge`, so a
> published file that defines `exceptions` or `key` **replaces that whole sub-array** rather than
> merging into it. A published `exceptions` block listing only `unauthorized` drops the package's
> 401 and 404 mappings entirely. Keep every category you want, or delete the key to inherit all of
> them.

## Routes

Nothing is registered for you. Put this in your routes file, inside whatever middleware group the
operations belong to:

```php
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;

Route::middleware('web')->group(function () {
    LaravelHttpController::registerQueries();    // GET  /query/{fqn}
    LaravelHttpController::registerCommands();   // POST /command/{fqn}
});
```

Both take a route prefix, defaulting to `query` and `command`, and both name the route they register
(`__query_route` and `__command_route`). `operations:codegen` and `operations:list` read the
registered URIs, and `operations:codegen` fails with *"The operation routes are not registered"* if
you skip this step.

Because you register them, the middleware group, authentication, throttling, session and CSRF
behaviour are entirely your application's choice — the adapter has no opinion and adds nothing to
the HTTP kernel.

**The route parameter must stay named `{fqn}`.** The generated client substitutes the operation key
into that placeholder literally, so a hand-registered route using any other name yields a client
requesting URLs that still contain `{fqn}` — no error anywhere, just 404s.

## Context

`context` names a class implementing `ContextFactory`, whose single method builds the `$context`
every handler and middleware receives:

```php
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ContextFactory;

final class OperationContextFactory implements ContextFactory
{
    public function createContextFromHttpRequest(Request $request): mixed
    {
        return new MyContext(user: $request->user());
    }
}
```

The class is resolved through the container, so it may take constructor arguments. Leave the config
`null` and every handler receives `null` as its context.

## Client

`client` names a class implementing `ClientFactory`, whose single method builds the `Client` every
handler and middleware receives — the collector for [client directives](client-directives.md):

```php
use Le0daniel\PhpTsBindings\Adapters\Laravel\Contracts\ClientFactory;

final class MobileAwareClientFactory implements ClientFactory
{
    public function createClientFromHttpRequest(Request $request): Client
    {
        return match ($request->header('X-Client-Id')) {
            'operations-spa' => new OperationSPAClient(),
            'mobile-app' => new MobileClient(),
            default => new NullClient(),
        };
    }
}
```

The class is resolved through the container, so it may take constructor arguments. Leave the config
`null` and the default `OperationClientFactory` applies: the header `X-Client-Id: operations-spa`
selects `OperationSPAClient`, every other request gets a `NullClient`.

## What the adapter decides for you

Everything the provider and the HTTP controller pick without asking.

### Wiring

- **Handlers and middleware are resolved through the application container** — the adapter always
  uses `PsrContainerAdapter($app)`, never `NewInstanceAdapter`. Constructor injection, singletons and
  contextual bindings all apply to your operation classes.
- **Keys are obfuscated by default**, with the pepper `"none"`. The adapter constructs
  `HashSha256KeyGenerator` with the pepper only, so it takes that class's defaults: an 8-character
  namespace segment and a 24-character name segment. Set `key.pepper` to something of your own, or
  `key.mode` to `plain` if you would rather read your own URLs.
- **`coerceQueryInput` stays `false` and is not configurable.** It does not need to be: the transport
  JSON-decodes every query parameter, so values arrive already typed. See
  [`ServerConfiguration`](operations.md#serverconfiguration) for what the flag does.
- **Discovery scans `discovery_path` recursively**, reflecting every declared class, and assumes one
  class per file. If the config key is missing entirely the provider falls back to `[]` — an empty
  registry, not an error.
- **The optimized registry is picked up automatically** when `bootstrap/cache/operations.php` exists.
  The path is hardcoded.
- **The provider is deferred.** It implements `DeferrableProvider` and only declares `TypeParser`,
  `LaravelHttpController`, `Preloader` and `operations.default_server`, so nothing — including the
  config merge — runs until one of those four is resolved. Console commands and `vendor:publish`
  work regardless; resolving the four bindings is how anything else reaches this package.

### Requests

- **Queries are GET, commands are POST.** No other verb is served.
- **Query input** is `$request->query->all()` with every *string* value passed through
  `json_decode()`, falling back to the raw string when it does not parse. Non-strings — what
  `?filter[a]=1` produces — are passed through untouched for the schema to reject.
- **Command input is `$request->json()->all()`**, so **the body must be JSON**. A form-encoded POST
  yields an empty array, which becomes a `null` input and almost certainly a 422.
- Empty input of either kind becomes `null`.
- **`X-Client-Id: operations-spa`** — exactly that value — selects `OperationSPAClient`. Every other
  request gets a `NullClient`, and [client directives](client-directives.md) go nowhere
  without warning. The generated client sends the header on every call. This is the default
  `OperationClientFactory`; the [`client` config key](#client) replaces it.

### Responses

- **Success is always HTTP 200**, with `{"success": true, "data": …}`. Failures use the error
  category's own status: 400, 401, 403, 404, 422 or 500.
- **The body is `RpcResult::jsonSerialize()`**, unchanged. The controller adds nothing to it outside
  [debug mode](#debug-mode), so what the generated client reads is what the core defined.
- `__metadata` is added when a middleware attached any, on either outcome. `__client` is added when
  the client produced directives — **on success only**: a handler that toasts and then throws must
  not have the browser announce work that did not happen, so a failure carries no directives at all.
- **Exception rendering bypasses Laravel entirely.** Nothing is thrown out of the controller. Every
  throwable behind an `RpcError` is handed to `ExceptionHandler::report()` — the cause, plus anything
  in `previous`, oldest first — so logging, Sentry and friends still fire, and then the error is
  serialized as the envelope above. Laravel's `render()`, `renderable()` handlers, `abort()` pages
  and the 419 CSRF redirect never run for an operation.
- **Validation errors are not Laravel's shape.** A 422 carries
  `{"code": 422, "type": "INVALID_INPUT", "details": {"fields": {"<dotted.path>": ["message"]}}}`,
  not `{"message": …, "errors": …}`. `Illuminate\Validation\ValidationException` is **not** mapped by
  default, so a `$request->validate()` inside a handler lands in a 500. Map it onto a category
  yourself with [`ServerConfiguration::withExceptions()`](operations.md#serverconfiguration) — or
  better, stop validating in the handler: the 422 is the schema's own verdict, and a rule the type
  cannot express belongs in a value object or in a domain error, per
  [Your own validation](errors.md#your-own-validation).

### CSRF and cookies

The generated client sends `Accept`, `X-Client-ID` and `Content-Type` — **no CSRF token** — and sets
no `credentials` option, so cookies ride the browser's `same-origin` default. Commands registered
inside the `web` group therefore need either a CSRF exemption or a custom `OperationClient` that
attaches the token. The default mapping of `TokenMismatchException` to 403 is the acknowledgement of
that: a rejected token reaches the frontend as an ordinary `AUTHORIZATION_ERROR` rather than as an
HTML redirect.

### Debug mode

> **`app.debug` changes behaviour.** With it on, every response gains a `__resolveInfo` key naming
> the handler, the middleware stack, the operation's fully qualified name and its type — successes
> included. Failures additionally carry `__debug` with the exception class, message, code, file,
> line and **full stack trace**, plus a `previous` list describing the earlier failures on the rare
> error that has any.
>
> Neither key is emitted in production, and neither appears in the generated types — but any
> environment with `APP_DEBUG=true` and a reachable route is handing all of that to whoever calls it.

## Artisan commands

| Command | Purpose |
|---|---|
| `operations:list` | Every registered operation with its URI, method, handler, Laravel middleware and operation middleware. |
| `operations:codegen {directory}` | Generate the TypeScript client. |
| `operations:optimize` | Compile the registry to `bootstrap/cache/operations.php`. |
| `operations:clear-optimize` | Remove it. |

### `operations:codegen`

```bash
php artisan operations:codegen resources/js/operations --with=tanstack-query,query-key,type-map
```

| Option | Effect |
|---|---|
| `--with=` | Turn a generator on. Repeatable, and comma-separated values are expanded. |
| `--without=` | Turn a default generator off. `--with` wins when a name appears in both. |
| `--custom=` | Add your own generator by class name, resolved through the container. |
| `--ignore=` | Skip a namespace, or one operation as `namespace.name`. |
| `--naming=` | How the generated functions are named. Default `name`. |
| `--verify` | Check for drift instead of writing, exiting 1 on any difference. Use it in CI. |

The command is a pass-through to `CodeGenerators::fromDefaults()`, so its flags are the names and
modes that class defines — see [the generators](typescript-client.md#generators) for what each one
writes. The names accepted by `--with` and `--without`:

| Name | Generator | Default |
|---|---|---|
| `types` | `EmitTypes` | on |
| `bindings` | `EmitOperationClientBindings` | on |
| `utils` | `EmitTypeUtils` | on |
| `operations-spa` | `EmitOperationsSpaClient` | on |
| `operations` | `EmitOperations` | on |
| `type-map` | `EmitTypeMap` | off |
| `tanstack-query` | `EmitTanstackQuery` | off |
| `query-key` | `EmitQueryKey` | off |

`--naming=` chooses how functions are named. Every mode but the last is one
`CodeGenerators::namingGenerator()` knows; `Class::method` is this command's own:

| Mode | `#[Query('users', 'get')]` becomes |
|---|---|
| `name` (default) | `get` |
| `fqn`, `operation-prefix` | `usersGet` — the two are the same rule |
| `namespace-postfix` | `getUsers` |
| `Class::method` | whatever your rule returns |

`Class::method` resolves `Class` through the container and calls it as an **instance** method with
the `TypedOperation`, despite the static-looking syntax. An unknown mode ends the run with a message
listing the valid ones.

Three details worth knowing:

- A relative `{directory}` resolves against `base_path()`, **not** the current working directory.
- `--ignore` takes the plain `namespace.name`, never the obfuscated key.
- The command always builds a fresh, eagerly-discovered registry, so it never reads
  `bootstrap/cache/operations.php` and never emits a client from a stale cache.

> `operations:codegen` removes every file it previously wrote under the target directory, identified
> by the `// generated by: php-ts-bindings` marker on the first line. Anything else is left alone,
> and a generated module colliding with an unmarked file of the same name is refused rather than
> overwritten. Upgrading from a version that predates the marker means deleting the output directory
> once.

## Production

```bash
php artisan operations:optimize
```

This compiles the registry to `bootstrap/cache/operations.php` — a hardcoded path — with every
schema pre-parsed, deduplicated and pooled. The provider picks the file up automatically when it
exists. `--id-length=` overrides `cache.idLength` for the run.

The command writes the file and then `require`s it, to prove it parses and that its ids do not
collide; if anything fails it reports the message and deletes the file rather than leaving a broken
cache for the next request.

Both commands are wired into `php artisan optimize` and `optimize:clear`, so a standard deploy picks
them up without extra steps. Run `operations:codegen --verify` in CI to catch a frontend that has
drifted from the backend.

A cache that no longer matches the code asking it fails loudly, at runtime, with an
`UnknownTypeKeyException` — regenerate it with `operations:optimize`, or drop it with
`operations:clear-optimize`.

> Operation keys are derived from `key.mode` and `key.pepper`, so changing either — including
> upgrading a version that changed how they are derived — invalidates both the cache and the
> generated client. Run `operations:optimize` and `operations:codegen` together.

See [Production](server.md#production) for the optimizer underneath, which is usable on its own.

## Preloading

[`Preloader`](server.md#preloading-a-query) is registered as a container singleton, built with the
same key generator as the registry, so injecting it is all that is needed:

```php
public function show(Preloader $preloader): Response
{
    return Inertia::render('Users', [
        'users' => $preloader->preload('users', 'get', ['id' => 1], $context),
    ]);
}
```

You get back `['response' => …, 'queryKey' => …]`, with a key built exactly as the generated
`--with=query-key` and `tanstack-query` code builds it, so a TanStack cache seeded with that pair
will not refetch.
