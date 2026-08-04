# PHP-TS Bindings

Type-safe RPC between a PHP backend and a TypeScript frontend, driven by the types you have already
written.

You annotate a method with `#[Query]` or `#[Command]` and type its input and output with PHPStan
annotations. From that, this library validates every incoming request against those types,
serializes the response to match them, and generates a TypeScript client whose call signatures are
those same types. There is no schema to declare, no resource class to maintain, and no second source
of truth to keep in sync — the PHPStan type *is* the contract.

```php
/**
 * @param array{id: UserId} $input
 * @return array{email: Email, slug: Slug}
 */
#[Query('users')]
public function get(array $input): array { /* ... */ }
```

```typescript
export type GetInput = {id:(number & Brand<"customerId">);};
export type GetResult = {email:(string & Brand<"email">);slug:string;};

const result = await get({id: userId});
```

**What it is not.** Not a validator — it proves the types your code declares, and nothing beyond
them. Not an ORM serializer. Not a schema DSL. If a rule cannot be expressed as a PHPStan type, this
library will not check it for you; [value objects](docs/types.md#value-objects) are where such rules
belong.

Requires **PHP 8.5** and nothing else — the core has no dependencies and no framework coupling. A
first-party Laravel adapter ships in the box and is entirely optional.

---

- [Install](#install)
- [Quickstart](#quickstart)
- [Core concepts](#core-concepts)
- [Defining operations](#defining-operations)
- [Middleware](#middleware)
- [Types](#types)
- [Errors](#errors)
- [The generated TypeScript client](#the-generated-typescript-client)
- [Client directives](#client-directives)
- [Laravel setup](#laravel-setup)
- [Production](#production)
- [Without a framework](#without-a-framework)
- [Extension points and exceptions](#extension-points-and-exceptions)

## Install

```bash
composer require le0daniel/php-ts-bindings
```

Then register the PHPStan extension, so static analysis understands the same utility types the
generator does — without it `Pick`, `Omit`, `BrandedString`, `BrandedInt` and `DateTimeString` do
not resolve:

```neon
# phpstan.neon
includes:
    - vendor/le0daniel/php-ts-bindings/extension.neon
```

On Laravel the service provider is auto-discovered; there is nothing else to register. See
[Laravel setup](#laravel-setup).

## Quickstart

Write a class of operations. The first parameter is the input, and its PHPStan type is what the
client must send. The return type is what the client receives.

```php
namespace App\Operations;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Command;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;

final class UserOperations
{
    /**
     * @param array{id: UserId} $input
     * @return array{email: Email, slug: Slug}
     */
    #[Query('users')]
    public function get(array $input): array
    {
        return [
            'email' => Email::fromStringValue('user@example.com'),
            'slug' => Slug::fromStringValue("user-{$input['id']->toIntValue()}"),
        ];
    }

    /**
     * @param array{name: string} $input
     * @return array{id: UserId}
     */
    #[Command('users')]
    public function create(array $input): array
    {
        return ['id' => UserId::fromIntValue(strlen($input['name']))];
    }
}
```

`UserId`, `Email` and `Slug` are [value objects](docs/types.md#value-objects) — classes backed by a
single primitive. `UserId` and `Email` carry a `#[Brand]`, so they are not interchangeable with a
plain `number` or `string` on the TypeScript side.

Generate the client:

```bash
php artisan operations:codegen resources/js/operations
```

You get a `users.ts` module, matching the namespace:

```typescript
export type GetResult = {email:(string & Brand<"email">);slug:string;};
export type GetInput = {id:(number & Brand<"customerId">);};
export type GetError = /* the operation's error union */;

export async function get(input: GetInput, options?: OperationOptions) { /* ... */ }
```

and call it:

```typescript
import {get} from './operations/users';

const result = await get({id: userId});
if (result.success) {
    result.data.email;   // (string & Brand<"email">)
} else {
    result.type;         // "INVALID_INPUT" | "NOT_FOUND" | "INTERNAL_ERROR" | ...
}
```

Passing `{id: 1}` is a compile error: `number` is not assignable to `UserId`'s branded type. Sending
it anyway is a 422 at runtime, because the server proves the same type it published.

## Core concepts

**`Server`** takes a registry of operations and runs one. Both methods are total — every
`Throwable`, including a failure resolving your handler, comes back as an `RpcError`:

```php
public function query(string $name, mixed $input, mixed $context, Client $client): RpcSuccess|RpcError
public function command(string $name, mixed $input, mixed $context, Client $client): RpcSuccess|RpcError
```

**`$name` is the operation's *key*, not its plain name.** An `OperationKeyGenerator` turns
`namespace` + `name` into what the client calls. The default is
`HashSha256KeyGenerator`, whose first constructor argument — a pepper — is **required**; it hashes
both parts, so a discovered `users.get` is reachable as an opaque key rather than as `users.get`.
Use `PlainlyExposedKeyGenerator` for literal keys. The generated TypeScript always embeds whichever
key the server produced, so this only matters when you call the server by hand.

Obfuscation is not a security boundary: it keeps your operation names out of the shipped bundle, and
that is all. Two operations colliding on a truncated hash is an error at discovery, not a silent
overwrite — widen `fnNameLength` if a real application ever hits it.

**`OperationRegistry`** holds the operations. `EagerlyLoadedOperationRegistry` discovers them by
scanning directories; schemas are parsed lazily, per operation, on first use.
`CachedOperationRegistry` is the compiled form for production — see [Production](#production).

**`ServerAdapter`** builds your handler classes and middleware. It is two methods —
`createController()` and `createMiddleware()` — and it is the seam for dependency injection:

| Adapter | Behaviour |
|---|---|
| `NewInstanceAdapter` | The default. Plain `new $className()`, so handlers take no constructor arguments. |
| `PsrContainerAdapter` | Resolves both through a PSR-11 container. |

Laravel wires `PsrContainerAdapter` to the application container for you. Implement the interface
yourself for a container that is not PSR-11, or to construct handlers some other way. Whatever it
does, a failure to resolve is caught and returned as an `RpcError` — that is part of what keeps
`query()` and `command()` total.

**The handler contract.** Your method is called with exactly three arguments, positionally:

```php
public function get(array $input, MyContext $context, Client $client): array
```

`$input` is the parsed, validated input — already hydrated into whatever your type declares. **Its
type is the whole input contract**, so the first parameter is the one that matters. `$context` is
whatever you passed to `Server::query()`; the library never touches it. `$client` is the
[side channel](#client-directives) back to the frontend.

You may declare a **prefix** of those three — `($input)` and `($input, $context)` are both fine — but
not a subset. `($input, Client $client)` receives the context in the client slot, so discovery
rejects it rather than letting it fail at runtime.

Middleware receives `ResolveInfo` alongside the input, describing the operation being run:
`namespace`, `name`, `operationType`, `className`, `methodName`, `middleware` (every class in the
stack) and `fullyQualifiedName`.

**Input is parsed, output is serialized.** Input is untrusted, so every claim its type makes is
proven. Output is checked against its declared type too, and an output that does not match is a 500 —
it is a bug in your code, not something the client can fix. The PHPStan *refinements* on top of the
type are not re-checked, because static analysis already established those. See
[refinements run on input, never on output](docs/types.md#refinements-run-on-input-never-on-output).

Both results carry metadata a middleware can attach with `withMetadata()` / `appendMetadata()`; the
Laravel adapter surfaces it as a `__metadata` key on the response.

## Defining operations

| Attribute | Target | What it does |
|---|---|---|
| `#[Query(namespace, name)]` | method | A read operation, served over GET. |
| `#[Command(namespace, name)]` | method | A write operation, served over POST. |
| `#[Middleware(class)]` | class, method, repeatable | Middleware to run around this operation. |
| `#[Throws(ExceptionClass, as: ?string)]` | method, repeatable | Declares an exception the operation may throw, optionally naming it for the client. |
| `#[ExposeAs(type)]` | exception class | The exception's own name, for every operation that declares it. |
| `#[Optional]` | property, parameter | The field may be absent from input. |
| `#[Castable(strategy)]` | class | A plain class may be built from input. |
| `#[Brand(name)]` | class | Makes the generated TypeScript type opaque. |
| `#[Named(name)]` | class | Exports the type once by name instead of inlining it. |

`namespace` defaults to `global` and becomes the generated TypeScript module, so it has to be a
usable file name: letters, digits, `-` and `_`. It accepts a `UnitEnum` as well as a string, so you
can keep namespaces in an enum — note that a *backed* enum contributes its value and a pure enum its
case name, so adding `: string` to an existing namespace enum changes every generated module and
wire key. `name` defaults to the method name and is a string only.

Two operations of the same type resolving to the same `namespace.name` fail discovery. A query and a
command *may* share one, but both land in the same generated module, so unless `--naming` tells them
apart the code generator rejects them rather than emit two functions of the same name.

`#[Brand]`, `#[Named]`, `#[Castable]` and `#[Optional]` are covered in
[the type reference](docs/types.md).

## Middleware

A middleware wraps the operation. Implement `MiddlewareContract`:

```php
use Le0daniel\PhpTsBindings\Contracts\MiddlewareContract;

/**
 * @implements MiddlewareContract<mixed>
 */
final class NameCheckingMiddleware implements MiddlewareContract
{
    #[Throws(InvalidNameException::class)]
    public function handle(
        mixed $input,
        Closure $next,
        mixed $context,
        ResolveInfo $info,
        Client $client,
    ): RpcSuccess|RpcError
    {
        if (is_array($input) && ($input['name'] ?? null) === 'invalid') {
            throw new InvalidNameException();
        }

        return $next($input);
    }
}
```

**`$next()` never throws.** A failure deeper in the pipeline is converted to an `RpcError` at the
ring where it happened and handed back to you as `$next()`'s return value, so post-processing runs
whether the operation succeeded or not.

Attach it per operation or per class. `#[Middleware]` takes one class and is repeatable, so stack it:

```php
#[Command('users')]
#[Middleware(AuthMiddleware::class)]
#[Middleware(NameCheckingMiddleware::class)]
public function create(array $input): array { /* ... */ }
```

or globally, for every operation on the server:

```php
new ServerConfiguration()->withMiddlewares(AuthMiddleware::class, LoggingMiddleware::class)
```

**Order is outermost first.** Global middleware wraps class-level `#[Middleware]`, which wraps
method-level, and within each group they run in declaration order. The first one listed is the first
to see the input and the last to see the result.

`#[Throws]` on a middleware's `handle()` contributes to the error union of every operation it wraps —
globally configured or attached with `#[Middleware]`, both count — so the generated TypeScript knows
about middleware failures too. It takes `as` like any other declaration, and when an operation and
its middleware declare the same exception, the operation's name wins.

### The rest of `ServerConfiguration`

The same object carries the server's other settings.

On Laravel, `withMiddlewares()` and `withExceptions()` are populated from `config/operations.php`.
`coerceQueryInput` is not, and deliberately: the Laravel transport JSON-encodes each query parameter
and decodes it again, so values arrive already typed and there is nothing to coerce. Everywhere else,
this object is where all three are set.

`withExceptions()` maps your exceptions onto the [error categories](#errors). Matching is
`instanceof`, so listing a base class covers its subclasses, and an omitted category is left
untouched:

```php
new ServerConfiguration()->withExceptions(
    notFound: [EntityNotFoundException::class],
    unauthenticated: [NotLoggedInException::class],
    unauthorized: [ForbiddenException::class],
)
```

Without this, nothing produces a 401, 403 or 404 except an unknown operation — every other
exception is a 500.

`coerceQueryInput` (default `false`) applies to **queries only**, and exists because a URL carries no
types. The generated client JSON-encodes each value and the Laravel adapter decodes it again, so
`?id=1` arrives as the integer `1` and nothing needs coercing. Turn this on when requests come from
somewhere that does not round-trip — a hand-written URL, a form, a transport of your own — and leaf
primitives are coerced to the declared type before validation instead of failing it:

```php
new ServerConfiguration(coerceQueryInput: true)
```

Because it applies to queries only, the same input shape validates differently depending on whether
it is reached through `#[Query]` or `#[Command]`. Coercion never invents a value: only scalars are
cast, and anything else is left for the schema to reject.

## Types

Most of what PHPStan can express about a shape, this library can parse, serialize and emit:

| PHPStan | TypeScript |
|---|---|
| `string`, `int`, `float`, `bool`, `null`, `mixed` | `string`, `number`, `number`, `boolean`, `null`, `unknown` |
| `numeric`, `scalar` | `(number)`, `(number\|boolean\|string)` |
| `'foo'`, `123`, `true`, `MyEnum::CASE` | `"foo"`, `123`, `true`, `"CASE"` |
| `array{name: string, age?: int}`, `object{name: string}` | `{age?:number;name:string;}` |
| `list<T>`, `T[]`, `array<int, T>` | `Array<T>` |
| `array<string, T>` | `Record<string,T>` |
| `array{string, int}` | `[string,number]` |
| `A\|B`, `?T` | `(A\|B)`, `(null\|T)` |
| `A&B` (shapes of the same kind) | `({a:string;}&{b:number;})` |
| `MyEnum` | `("OPEN"\|"SHIPPED")` |
| `DateTimeImmutable`, `DateTimeString<'Y-m-d'>` | `string` |
| `positive-int`, `non-empty-string`, … | `number`, `string` — refinement enforced server-side |

Object properties are emitted in a canonical order, sorted by name — which is why `age` comes first
above. Declaration order does not reach the client, so reordering a PHP property is not a change to
the generated type.

**An enum travels as its case names, not its backing values.** `MyEnum` emits `("OPEN"|"SHIPPED")`
even when it is `enum MyEnum: string { case OPEN = 'open'; }`. A backed enum that should travel as
its backing value opts in by implementing `StringValueObject` — see
[value objects](docs/types.md#value-objects).

Local and imported types work too: `@phpstan-type` and `@phpstan-import-type` are resolved against
the declaring class, as are `use` statements and generics.

### Not supported

The parser understands a subset of PHPStan, not all of it. These are valid PHPStan that it rejects,
with an `InvalidSyntaxException` when the schema is parsed:

`class-string` · `key-of<T>` · `value-of<T>` · `int-mask` · `int-mask-of` · `callable(…)` ·
`Closure(…): T` · `iterable` · `array{foo: int, ...}` (unsealed) · `array{}` ·
`($x is int ? A : B)` · `Foo<T = int>` · `$this` · `static` · `self` ·
bare `array` / `list` / `non-empty-array` (without generics)

Two traps worth knowing up front. Bare `object` is not an alias for `unknown` — it is a syntax
error; write `object{…}` with the shape. And bare `array` is not `Array<unknown>`: PHPStan reads it
as `array<mixed, mixed>`, which permits string keys, so there is no one TypeScript type it means.
Write `list<T>`, `array<int, T>` or `array<string, T>`.

**[→ Full type reference](docs/types.md)** — refinements, utility types (`Pick`, `Omit`,
`BrandedString`, `DateTimeString`), value objects, `#[Castable]`, brands and named types, and the
[full list of what is not supported](docs/types.md#not-supported).

## Errors

Every failure the client can see is one of six categories:

| Code | `type` | When |
|---|---|---|
| 422 | `INVALID_INPUT` | The input did not match its type |
| 401 | `AUTHENTICATION_ERROR` | An exception you mapped as unauthenticated |
| 403 | `AUTHORIZATION_ERROR` | An exception you mapped as unauthorized |
| 404 | `NOT_FOUND` | Unknown operation, or an exception you mapped as not-found |
| 400 | `DOMAIN_ERROR` | An exception you declared with `#[Throws]` *and* gave a name |
| 500 | `INTERNAL_ERROR` | Anything else, including an output that did not match its type |

The table is in resolution order, and the first match wins. That order is why `DOMAIN_ERROR` sits
second to last: an exception you have explicitly mapped onto a category stays in that category even
when it is named for the client. Anything unrecognised is a 500 — an exception is never exposed by
accident.

**Exposing a domain error takes a declaration and a name.** The operation declares that it can
throw the exception, and something gives that exception a name the client sees. The exception can
carry its own:

```php
#[ExposeAs('invalid_name')]
final class InvalidNameException extends Exception {}

#[Command('users')]
#[Throws(InvalidNameException::class)]
public function create(array $input): array { /* ... */ }
```

```json
{"success": false, "code": 400, "type": "DOMAIN_ERROR", "details": {"type": "invalid_name"}}
```

Or the declaration can name it on the spot with `as`, which needs no `#[ExposeAs]` at all — the
point being that the exception does not have to be yours to annotate:

```php
#[Command('users')]
#[Throws(InvalidNameException::class, as: 'invalid-name')]
public function create(array $input): array { /* ... */ }
```

`as` always wins over `#[ExposeAs]`, so the same exception can read differently per operation. What
`as` does not do is skip the declaration: an exception no operation declares with `#[Throws]` is
still a 500, and so is one that is declared but named nowhere.

Because both the runtime and the code generator read those attributes from the same place, the
generated error union cannot drift from the responses it describes. An operation that declares
nothing gets:

```typescript
export type CreateError =
    {code: 422, type: "INVALID_INPUT", details: {type: "INVALID_INPUT"; fields: Record<string, string[]>}}
  | {code: 404, type: "NOT_FOUND", details: {type: "NOT_FOUND"}}
  | {code: 500, type: "INTERNAL_ERROR", details: {type: "INTERNAL_SERVER_ERROR"}};
```

The 401 and 403 branches appear only once you have actually mapped exceptions onto them, so the
union describes what this server can really produce. Validation failures carry `fields`, keyed by
dotted path (`__root` for the top level) with localization keys as values, e.g.
`{"email": ["validation.not_empty_string"]}`.

**Your own validation can ride the same wire.** This library proves types and refuses to grow into a
validator, but the 422 shape is a perfectly good transport for the rules it will not check for you:
`InvalidInputException::createFromMessages(['email' => ['Already taken']])` produces one from any
field-to-message map. Throw it from a handler or a middleware and the client reads it exactly like a
type failure.

## The generated TypeScript client

`operations:codegen <directory>` writes a self-contained client. Nothing is published to npm; the
code lives in your repo.

```
<directory>/
  lib/types.ts             Success/Failure/Result, Brand, every #[Named] alias
  lib/OperationClient.ts   the transport interface
  lib/DefaultClient.ts     a fetch implementation of it
  lib/OperationException.ts
  lib/bindings.ts          createDefaultClient, setClient, executeOperation, throwOnFailure
  lib/utils.ts             queryKey and the client-directive type guards
  <namespace>.ts           one module per namespace, one function per operation
```

That is the default output. The [optional generators](#optional-generators) add to it: `type-map`
writes one more file, the other two write into the `<namespace>.ts` modules that are already there.

Every generated file opens with `// generated by: php-ts-bindings`. That marker is what
`operations:codegen` uses to tell its own output from anything else in the directory: it removes
only files carrying it, and refuses to overwrite one that does not.

The envelope every call resolves to:

```typescript
export type Success<T> = {success: true, data: T}
export type Failure<E extends {code: number}> = {success: false} & E;
export type Result<T, E extends {code: number} = never> = Success<T> | Failure<E>;

// what a generated function actually returns — see Client directives
export type WithClientDirectives<T> = T & {__client?: unknown};
```

Wire it up once:

```typescript
import {createDefaultClient, setClient} from './operations/lib/bindings';

setClient(createDefaultClient());
setClient(createDefaultClient(fetch, {baseUrl: 'https://api.example.com', timeoutMs: 5_000}));
```

`setClient()` sets one module-global client, so it has to run before the first call — otherwise you
get `Error('No client set')` at whichever call site happened to be first.

Every generated function takes an optional second argument:

```typescript
export type OperationOptions = {signal?: AbortSignal; timeoutMs?: number; client?: OperationClient};
```

`DefaultClient` sends queries as GET with each input value JSON-encoded into a query parameter, and
commands as POST with a JSON body. `signal` and `timeoutMs` are joined into one `AbortSignal`, and a
per-call `timeoutMs` overrides the client's default (10s unless you set one). `registerHook(hook)`
runs a callback on every response and returns a function that unregisters it. Swap the whole
transport by implementing `OperationClient` — `setClient()` and the per-call `options.client` both
take one.

`throwOnFailure(result)` narrows a `Result` to its success branch and throws an `OperationException`
otherwise, for call sites that would rather not branch. A `catch` variable is `unknown` in TypeScript
whatever was thrown, so name the operation's error union at the guard to get it back:

```typescript
try {
    const result = await get({id: userId});
    throwOnFailure(result);
    return result.data;
} catch (e) {
    if (OperationException.is<GetError>(e)) {
        e.cause.type;   // "INVALID_INPUT" | "NOT_FOUND" | ...
        e.code;         // the HTTP code, 500 if the payload had none
    }
    throw e;
}
```

### Optional generators

Three more generators ship, all off by default:

```bash
php artisan operations:codegen resources/js/operations --with=tanstack-query,query-key,type-map
```

`tanstack-query` emits `<name>QueryOptions()` and `use<Name>Query()` for `@tanstack/react-query`, and
`query-key` emits standalone query keys — both **only for queries**, since a command has nothing to
cache. `type-map` writes `lib/type-map.ts`, exporting a `TypeMap` that maps every operation to its
input, output and error types, split into `{query: …, command: …}` and keyed by `namespace.name`.

`--without=` drops a default generator, named as `types`, `bindings`, `utils` or `operations`.
`--ignore=` skips a namespace, or one operation as `namespace.name`.

`--naming=` chooses how functions are named:

| Mode | `#[Query('users', 'get')]` becomes |
|---|---|
| `name` (default) | `get` |
| `fqn`, `operation-prefix` | `usersGet` — the two are the same rule |
| `namespace-postfix` | `getUsers` |
| `Class::method` | whatever your rule returns |

`Class::method` resolves `Class` through the container and calls it as an **instance** method with
the `TypedOperation`, despite the static-looking syntax.

Write your own generator by implementing `GeneratesLibFiles` (gets every operation, writes shared
lib files) or `GeneratesOperationCode` (gets one operation, writes its code) and passing it with
`--custom=My\Generator`. It is resolved through the container, so it may take constructor arguments.

Add `DependsOn` to declare the generators yours needs: the run fails early if one is missing, and
hands you the resolved instances through `setDependencies()`. That is how to reference what another
generator emitted — ask `EmitOperations` for `inputTypeName($operation)` rather than rebuilding the
name and hoping it matches, and ask `EmitTypes` for `importFromTypes(types: ['Order'])` rather than
writing the module specifier yourself. Because those methods are not static, an import can only ever
name a file a registered generator actually writes.

Hand your imports to `TypescriptFile` instead of writing `import` lines into the code: only then are
they merged with what the other generators contribute to the same file, and only then is the path
resolved for a file that lands in `lib/`.

A few contracts worth knowing when writing one:

- A `GeneratesLibFiles` key is a bare module name — `[a-zA-Z0-9_-]+`, no `.ts` — and always lands in
  `lib/`. Several generators may return the same key; their output accumulates rather than
  overwriting.
- `TypedOperation::$hasInput` is false when the operation's input type is `null`. Every generator
  that emits a signature has to drop the argument in that case.
- Return `null` from `generateOperationCode()` to emit nothing for an operation.

## Client directives

Optional, and unrelated to type safety: the `Client` passed to every handler is a side channel for
telling a single-page app what to do alongside the data.

```php
public function create(array $input, mixed $context, Client $client): array
{
    $client->success('Saved');
    $client->redirect('/docs/123', reload: true);
    $client->invalidate('users', '123');

    return ['id' => '123'];
}
```

This is the one place the library ships a specific implementation of an extension point rather than
a contract. `OperationSPAClient` is picked when the request carries `X-Client-Id: operations-spa` —
exactly that header, exactly that value. Any other request gets a `NullClient` whose every method is
a no-op, so handlers never need to know which kind is on the other end, and nothing warns when a
directive goes nowhere.

Under `operations-spa` those calls land in a `__client` key next to the data:

```json
{
  "success": true,
  "data": {"id": "123"},
  "__client": {
    "redirect": {"url": "/docs/123", "reload": true},
    "toasts": [{"type": "success", "message": "Saved"}],
    "invalidations": [["users", "123"]],
    "type": "operations-spa"
  }
}
```

The full interface is `redirect()`, `invalidate()`, `toast()`, and one shorthand per toast type —
`success()`, `error()`, `warning()`, `alert()` and `info()`. Keys are only present when something
called for them. A transport of your own emits the same payload by asking the client for it:
`SerializableClient::serializeToArray()`.

**`__client` is typed `unknown` on purpose.** `Client` is an extension point — your own
implementation may define an entirely different set of directives under a different schema — so the
generated types decline to commit to a shape they cannot know. `OperationSPAClient` is the subset
this library deems useful and ships, and `lib/utils.ts` narrows to it with `isSpaClientDirectives()`,
`isClientToast()` and `isClientRedirect()`. Write your own guard for your own directives; that is the
same "no dishonest types" rule that makes the generator throw rather than emit a placeholder.

## Laravel setup

**1. The provider is auto-discovered.** Nothing to register.

**2. Publish the config.**

```bash
php artisan vendor:publish --provider="Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelServiceProvider"
```

`config/operations.php`:

| Key | Default | Purpose |
|---|---|---|
| `discovery_path` | `app_path('Operations')` | Where operations are discovered. |
| `context` | `null` | A `ContextFactory` class, building the `$context` every handler receives from the request. |
| `key.mode` | `obfuscate` | `obfuscate`, `plain`, or `custom` with `key.className`. |
| `key.pepper` | `"none"` | Salt for `obfuscate` — the literal string `none`, not "no pepper". |
| `middleware` | `[]` | Global `MiddlewareContract` classes, run on every operation. |
| `exceptions.not_found` | Laravel's model-not-found exceptions | Mapped to 404. |
| `exceptions.unauthenticated` | `AuthenticationException` | Mapped to 401. |
| `exceptions.unauthorized` | `AuthorizationException`, `TokenMismatchException` | Mapped to 403. |
| `cache.idLength` | `10` | Id length used by the production cache. |

Exception matching is `instanceof`, so listing a base class covers its subclasses. An unrecognised
`key.mode` is an error rather than a fallback, because silently picking a different one would change
every key in the application.

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

Leave it `null` and every handler receives `null` as its context.

> **Debug mode changes behaviour.** With `app.debug` on, a `LocalMetadataMiddleware` is prepended to
> every operation and responses gain a `__metadata` key with the raw input, the context class and
> per-middleware timings; failures additionally carry `__info` and `__debug` with the exception
> message, file, line and full stack trace. None of that is emitted in production, and none of it is
> in the generated types.

**3. Register the routes.** Nothing is registered for you — put this in your routes file, inside
whatever middleware group the operations belong to:

```php
use Le0daniel\PhpTsBindings\Adapters\Laravel\LaravelHttpController;

Route::middleware('web')->group(function () {
    LaravelHttpController::registerQueries();    // GET  /query/{fqn}
    LaravelHttpController::registerCommands();   // POST /command/{fqn}
});
```

Both take a route prefix, defaulting to `query` and `command`. `operations:codegen` reads the
registered URIs to build the client, and fails with *"The operation routes are not registered"* if
you skip this step.

**The route parameter must stay named `{fqn}`.** The generated client substitutes the operation key
into that placeholder literally, so a hand-registered route using any other name yields a client
requesting URLs that still contain `{fqn}` — no error anywhere, just 404s.

**4. Write an operation** in `app/Operations`, as in the [quickstart](#quickstart).

**5. Generate the client.**

```bash
php artisan operations:codegen resources/js/operations
```

### Commands

| Command | Purpose |
|---|---|
| `operations:list` | Every registered operation with its URI, method and handler. |
| `operations:codegen {directory}` | Generate the TypeScript client. `--verify` checks for drift instead of writing — use it in CI. |
| `operations:optimize` | Compile the registry to `bootstrap/cache/operations.php`. `--id-length=` overrides `cache.idLength` for the run. |
| `operations:clear-optimize` | Remove it. |

The last two are wired into `php artisan optimize` and `optimize:clear`.

> `operations:codegen` removes every file it previously wrote under the target directory, identified
> by the `// generated by: php-ts-bindings` marker on the first line. Anything else is left alone,
> and a generated module colliding with an unmarked file of the same name is refused rather than
> overwritten. Upgrading from a version that predates the marker means deleting the output directory
> once.

### Preloading a query

`Preloader` runs a query server-side during the request that renders the page, so the data is in the
page instead of being fetched after it loads. It is a core class —
`Le0daniel\PhpTsBindings\Server\Preloader` — and on Laravel it is resolved from the container:

```php
public function show(Preloader $preloader): Response
{
    return Inertia::render('Users', [
        'users' => $preloader->preload('users', 'get', ['id' => 1], $context),
    ]);
}
```

You get back `['response' => …, 'queryKey' => ['users', 'get', ['id' => 1]]]`. The key is built the
same way the generated `--with=query-key` and `tanstack-query` code builds it, so a TanStack cache
seeded with that pair will not refetch. The input is part of the key whenever the operation has one,
even when the value is `null`; an operation whose input type *is* `null` gets a two-element key.

`preloadMany()` takes several at once, as
`[['namespace' => …, 'name' => …, 'input' => …], …]`. A query that fails throws a `SchemaException` —
this is your own code calling your own operation, not untrusted input.

Constructed by hand, `Preloader` takes the `Server` and an `OperationKeyGenerator`. That generator
has to be the one the server's registry uses, or the key it derives names no operation and every
preload throws.

## Production

Reflecting and parsing every schema on every request is real overhead. Compile the whole registry
once, at deploy time:

```bash
php artisan operations:optimize
```

This writes `bootstrap/cache/operations.php` with every schema pre-parsed, deduplicated and pooled —
shared structs are emitted once and referenced, and unions are reordered for faster dispatch. The
service provider picks the file up automatically when it exists. Run `operations:codegen --verify` in
CI to catch a frontend that has drifted from the backend.

A cache that no longer matches the code asking it fails loudly, at runtime, with an
`UnknownTypeKeyException` — regenerate it with `operations:optimize`, or drop it with
`operations:clear-optimize`.

> Operation keys are derived from `key.mode` and `key.pepper`, so changing either — including
> upgrading a version that changed how they are derived — invalidates both the cache and the
> generated client. Run `operations:optimize` and `operations:codegen` together.

Outside Laravel, or for schemas that are not operations, the same optimizer is available directly:

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

## Without a framework

The core knows nothing about Laravel — nothing outside `src/Adapters/` does. Build a server, run an
operation, and shape the response however you like.

The example below uses `PsrContainerAdapter`, which needs `psr/container` (a `suggest`, not a
dependency). Drop it and the default `NewInstanceAdapter` constructs handlers with `new`, which needs
nothing at all.

```php
use Le0daniel\PhpTsBindings\Server\Adapters\PsrContainerAdapter;
use Le0daniel\PhpTsBindings\Server\Client\NullClient;
use Le0daniel\PhpTsBindings\Server\Data\RpcSuccess;
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

if ($result instanceof RpcSuccess) {
    respondJson(200, ['success' => true, 'data' => $result->data]);
} else {
    respondJson($result->type->value, [
        'success' => false,
        'code' => $result->type->value,
        'type' => $result->type->name,
        'details' => $result->details,
    ]);
}
```

`$result->cause` is the underlying `Throwable` on every error, ready to hand to your reporter. On the
rare occasion that working out how to present an error *itself* failed — a stale middleware class
name, say — `$result->presentationFailure` holds that second exception; it is null otherwise.

To emit client directives, pass an `OperationSPAClient` instead of a `NullClient` and ask it for the
payload:

```php
use Le0daniel\PhpTsBindings\Contracts\SerializableClient;

$client = new OperationSPAClient();
$result = $server->command('users.create', $input, $myContext, $client);

$body = ['success' => true, 'data' => $result->data];
if ($client instanceof SerializableClient && $directives = $client->serializeToArray()) {
    $body['__client'] = $directives;
}
```

To generate the client, hand the same `Server` to `TypescriptServerCodeGenerator` with the URL
patterns your router uses:

```php
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\CodeGen\Utils\OutputDirectory;

$files = new TypescriptServerCodeGenerator([
    new EmitTypes(),
    new EmitOperationClientBindings(),
    new EmitTypeUtils(),
    // Takes an optional Closure(TypedOperation): string — the framework-free --naming.
    new EmitOperations(),
])->generate($server, new ServerMetadata('/query/{fqn}', '/command/{fqn}'));

// Creates lib/, prunes the modules it wrote for operations that no longer exist, and leaves
// anything it did not write alone. OutputDirectory::verify() is the same rules without writing —
// it returns one message per problem, and an empty list means the directory is up to date.
OutputDirectory::write('resources/js/operations', $files);
```

`generate()` takes a third argument, a list of namespaces (or `namespace.name` operations) to skip —
the equivalent of `--ignore=`.

The lower-level `TypeParser`, `SchemaExecutor` and `TypescriptGenerator` are public too, if you want
to parse and emit types without the RPC layer at all — see [the type reference](docs/types.md).

## Extension points and exceptions

The interfaces meant to be implemented by you:

| Contract | For |
|---|---|
| `MiddlewareContract` | Wrapping an operation. |
| `ServerAdapter` | Constructing handlers and middleware — the DI seam. |
| `OperationKeyGenerator` | Turning `namespace` + `name` into the key the client calls. |
| `OperationRegistry` | Holding operations, if neither shipped registry fits. |
| `Client` / `SerializableClient` | Your own side channel and its wire payload. |
| `StringValueObject` / `IntValueObject` | A class that travels as one primitive. |
| `GeneratesLibFiles` / `GeneratesOperationCode` / `DependsOn` | Adding to the generated client. |
| `ContextFactory` | Building `$context` from a request (Laravel adapter only). |

Two more knobs on discovery: `new OperationDiscovery($filterFn)` takes a closure returning `false` to
keep an operation out of the registry, and `EagerlyLoadedOperationRegistry::withClasses([...])`
registers a list of classes instead of scanning directories.

**Exceptions.** Everything this library throws implements `PhpTsBindingsException`, so one `catch`
covers all of it. Below that are three subsystem bases:

| Exception | Thrown when |
|---|---|
| `ParserException` | A schema cannot be built — includes `InvalidSyntaxException` (the type is not in the supported subset), `UnexpectedCharacterException` (it does not lex) and `UnknownTypeKeyException` (the optimized cache no longer matches the code). |
| `SchemaException` | An operation is malformed — a name or key collision, a bad handler signature, a class that is not middleware. `InvalidInputException`, `InvalidOutputException` and `OperationNotFoundException` extend it and reach you as `RpcError::$cause`. |
| `CodeGenException` | Generation cannot produce valid output — includes `UnsupportedTypeException` (no honest TypeScript for a schema), `InvalidStringLiteralException` (a brand or alias is not an identifier) and `InvalidGeneratorDependencies` (whose `$messages` names each missing generator). |

Nothing is thrown out of `Server::query()` or `Server::command()` — both are total, and every
`Throwable` comes back as an `RpcError`. The exceptions above surface at discovery, at parse time or
during code generation instead, which is to say: at build time, not at request time.

## Contributing

```bash
composer test          # pest
composer check:types   # phpstan, level 8
composer check:all
```

`tests/ts-output/` holds a committed sample of generated client code plus a hand-written consumer of
it, and `composer test` verifies that the generators still produce exactly those bytes. After
changing a code generator, regenerate it:

```bash
composer codegen:fixture   # regenerate tests/ts-output/generated, then tsc --noEmit over it
```

That step needs node; it is the only one that does. Commit the regenerated files — a change that
compiles is the point of the fixture.
