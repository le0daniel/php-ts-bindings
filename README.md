# PHP-TS Bindings

Type-safe RPC between a PHP backend and a TypeScript frontend, driven by the types you have already
written.

The goal is what server actions give a Next.js app — call a typed function on the client, have it
run on the server — built for PHP, and split explicitly into **queries** and **commands** (CQRS:
queries read, commands write). You annotate a method with `#[Query]` or `#[Command]` and type its
input and output with PHPStan annotations. From that, this library validates every incoming request
against those types, serializes the response to match them, and generates a TypeScript client whose
call signatures are those same types. There is no schema to declare, no resource class to maintain,
and no second source of truth to keep in sync — the PHPStan type *is* the contract.

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

Requires **PHP 8.5** and nothing else — no dependencies, no framework coupling on either side. Pair
it with React, Angular or vanilla TypeScript on the front, and any PHP framework or none on the
back. A first-party [Laravel adapter](docs/laravel.md) ships in the box and is entirely optional.

## What this library is not

Every line here is a decision, not a gap — [the decisions](#the-decisions) below carries the
reasoning for each. Read this before investing; it is the complete list of hard edges.

- **Not a validator.** It proves the types your code declares, and nothing beyond them. Rich
  validation is still needed — and it is not a middleware concern: it belongs in
  [value objects](docs/types.md#value-objects) and DTOs.
- **Not an ORM serializer.** It does not work with Eloquent models or Laravel Collections, on
  purpose. Return plain PHP objects a type checker can see through.
- **Not a framework.** No routing, no transport, no HTTP layer decided for you — and JSON only:
  streams, files and other non-JSON responses are out of scope.
- **Not frontend-opinionated.** The generated client is plain TypeScript with zero runtime
  dependencies; wire it into React, Angular, vanilla — anything.
- **Not useful without PHPStan.** Run it at level 6 or above, or the annotations this library
  trusts as the contract are unchecked claims.
- **Not all of PHPStan.** The parser understands a deliberate subset; bare `array` and bare
  `object` are rejected outright. [→ Types](#types)

## Documentation

This page is the overview: install it, run it without a framework, and understand why it behaves the
way it does. Each subsystem has its own reference.

| Document | Covers |
|---|---|
| [Types](docs/types.md) | The supported PHPStan subset, refinements, utility types, value objects, `#[Castable]`, brands and named types. |
| [Operations](docs/operations.md) | The attributes, the handler contract, middleware, `ServerConfiguration`. |
| [Errors](docs/errors.md) | The seven categories, the client error, exposing a domain error, the generated union, and the exceptions this library throws. |
| [The server](docs/server.md) | `Server`, operation keys, registries, DI, serving HTTP, preloading, the production cache, extension points. |
| [The TypeScript client](docs/typescript-client.md) | What codegen writes, the envelope, the transport, all eight generators, writing your own. |
| [Client directives](docs/client-directives.md) | The optional `Client` side channel for toasts, redirects and cache invalidation. |
| [The Laravel adapter](docs/laravel.md) | Config, routes, context, the `operations:*` artisan commands. |

On this page: [Install](#install) · [Quickstart](#quickstart) · [Architecture](#architecture) ·
[Errors](#errors) · [Types](#types) · [Contributing](#contributing)

## Install

```bash
composer require le0daniel/php-ts-bindings
```

Then register the PHPStan extension, so static analysis understands the same utility types the
generator does — without it `Pick`, `Omit`, `Named`, `Branded`, `BrandedString`, `BrandedInt` and
`DateTimeString` do not resolve:

```neon
# phpstan.neon
includes:
    - vendor/le0daniel/php-ts-bindings/extension.neon
```

The extension is half of it; the level is the other half. This library proves at runtime exactly
what your annotations claim — but only PHPStan proves that your annotations match your code. Run it
at **level 6 or above** (level 6 is where missing parameter and return types start being reported;
this library itself holds level 8). Below that, the contract your client compiles against has no
witness on the PHP side.

**On Laravel**, the service provider is auto-discovered, `config/operations.php` is publishable, and
four `operations:*` artisan commands handle discovery, code generation and the production cache. The
adapter wires this library up, it does not replace it — everything on this page still applies.
**[→ The Laravel adapter](docs/laravel.md)**

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

**Build a server over them, and generate the client.** Run this from a script you commit — it is a
build step, not something the server does at runtime.

```php
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\CodeGen\Utils\OutputDirectory;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;

$server = new Server(
    EagerlyLoadedOperationRegistry::eagerlyDiscover(
        __DIR__ . '/app/Operations',
        keyGenerator: new PlainlyExposedKeyGenerator(),
    ),
);

$files = new TypescriptServerCodeGenerator(
    CodeGenerators::fromDefaults('name'),
)->generate($server, new ServerMetadata(
    '/query/{key}',
    '/command/{key}',
    $server->configuration,
));

OutputDirectory::write(__DIR__ . '/resources/js/operations', $files);
```

`CodeGenerators::fromDefaults()` builds the five generators that are on by default, and `'name'` is
the rule that names the generated functions. `with:` and `without:` change the set — three more ship
opt-in — and what it returns is a plain list, so you can append your own or skip the factory and pass
your own array. See [the generators](docs/typescript-client.md#generators) for the whole menu.

The two URLs are the routes *your* transport serves; `{key}` is where the operation key goes, and
both are required to contain it. The configuration rides along because which error categories the
generated `Failure` union names depends on how this server maps exceptions.

**Serve those two routes.** One GET for queries, one POST for commands. `jsonSerialize()` is the
whole envelope the generated client reads, so a transport is two lines:

```php
use Le0daniel\PhpTsBindings\Server\Client\NullClient;

// GET /query/{key}  — each query parameter JSON-decoded back into a value
$result = $server->query($key, $input, $myContext, new NullClient());

// POST /command/{key}  — the JSON body
$result = $server->command($key, $input, $myContext, new NullClient());

respondJson($result->statusCode, $result->jsonSerialize());
```

Neither call ever throws — see [the server](docs/server.md#serving-operations-over-http) for the
full wiring, dependency injection and error reporting. `$myContext` and that `NullClient` are two of
the three arguments every handler receives; [the three arguments](#the-three-arguments) below is
what they mean.

**You get a `users.ts` module**, matching the namespace:

```typescript
export type GetResult = {email:(string & Brand<"email">);slug:string;};
export type GetInput = {id:(number & Brand<"customerId">);};
export type GetDomainErrors = /* the names this operation exposed, or never */;

export async function get(input: GetInput, options?: OperationOptions) { /* ... */ }
```

and call it:

```typescript
import {get} from './operations/users';

const result = await get({id: userId});
if (result.success) {
    result.data.email;   // (string & Brand<"email">)
} else {
    result.type;         // "INVALID_INPUT" | "NOT_FOUND" | "INTERNAL_ERROR" | "CLIENT_ERROR" | ...
}
```

Passing `{id: 1}` is a compile error: `number` is not assignable to `UserId`'s branded type. Sending
it anyway is a 422 at runtime, because the server proves the same type it published.

## Architecture

### One request through the server

**`Server`** takes a registry of operations and runs one. Both methods are total — every
`Throwable`, including a failure resolving your handler, comes back as an `RpcError`:

```php
public function query(string $name, mixed $input, mixed $context, Client $client): RpcSuccess|RpcError
public function command(string $name, mixed $input, mixed $context, Client $client): RpcSuccess|RpcError
```

**Input is parsed, output is serialized.** Input arrives from outside, so every claim its type
makes is proven before your handler sees it — a mismatch is a 422. Output is your own code, so an
output that does not match its type is a 500 rather than something the client is asked to handle.
The PHPStan *refinements* on top of a type (`positive-int`, `non-empty-string`) are checked on the
way in only, because static analysis already established them on the way out.

**`$key` is the operation's *key*, not its plain name.** An `OperationKeyGenerator` turns
`namespace` + `name` into what the client calls: `PlainlyExposedKeyGenerator` gives literal keys,
`HashSha256KeyGenerator` opaque ones — which is not a security boundary, it only keeps your
operation names out of the shipped bundle. The generated TypeScript always embeds whichever key the
server produced, so this only matters when you call the server by hand — but
[pass one explicitly](docs/server.md#operation-keys), because the discovery default is peppered with
a publicly known string.

**`OperationRegistry`** holds the operations: `EagerlyLoadedOperationRegistry` discovers them by
scanning directories (schemas are parsed lazily, per operation, on first use), and
`CachedOperationRegistry` is the compiled form for [production](docs/server.md#production).
**`ServerAdapter`** builds your handler classes and middleware — two methods, and the seam for
dependency injection: `NewInstanceAdapter` is the default, `PsrContainerAdapter` resolves through a
PSR-11 container. A failure to resolve is caught and returned as an `RpcError`, which is part of
what keeps the server total.

**`RpcResult`** is the interface both outcomes implement. It carries `statusCode` — 200 on success,
the error category's own code otherwise — and it is `JsonSerializable`: `jsonSerialize()` produces
the whole envelope the generated client reads, so a transport is a status code and a body.
Middleware can attach metadata; it travels under `__metadata` and the library puts nothing in it.

**[→ The server](docs/server.md)** for keys, registries, HTTP, preloading and the production cache.
**[→ Operations](docs/operations.md)** for the attributes, the full signature rules and middleware.

### The three arguments

Your method is called with exactly three arguments, positionally — input, context, client:

```php
public function get(array $input, MyContext $context, Client $client): array
```

You may declare a prefix of the three, never a subset: `($input)` and `($input, $context)` are
fine, skipping `$context` to reach `$client` is not. An operation that takes no input types its
parameter as `null`, and every generator drops the argument.

**`$input` is the request.** Parsed, validated, hydrated into whatever your type declares. Its
PHPStan type is the whole input contract — by the time your handler runs, every claim that type
makes has been proven.

**`$context` is everything else the operation needs.** To the library it is `mixed`: whatever you
pass to `Server::query()` arrives untouched. Put in it what your operations require — data from the
actual request, the authenticated user, the tenant. On Laravel, a
[`ContextFactory`](docs/laravel.md#context) builds it per request.

**`$client` is the side channel back to the frontend.** Not for data — for what rides alongside it:
toasts, redirects, cache invalidations. The interface is closed — `toast()`, the `success()` /
`error()` / `warning()` / `alert()` / `info()` shorthands, `redirect()` and `invalidate()`; there is
no arbitrary-directive method. And the library ships the channel, not the behavior: nothing pops up
until *you* implement the hooks on the frontend —
[`registerHook()`](docs/typescript-client.md#wiring-up-the-transport) from the generated bindings
sees every envelope, and `containsOperationSpaPayload()` narrows the deliberately-`unknown` `__client`
into typed toasts, a redirect and invalidation keys. What a toast looks like, or what an
invalidation invalidates, is your frontend's decision.
**[→ Client directives](docs/client-directives.md)**

**Context and client are the only two mutable things in the pipeline.** Both are created once per
request and passed through as-is — middleware and handler see and mutate the same two objects, and
they are the designated seams for state and side effects. Everything else moves by value: the input
is a freshly parsed value, and what comes back is a new envelope.

### The decisions

**The PHPStan type is the contract.** No schema DSL, no resource class, no generated PHP to keep
beside your code. The annotation you already wrote for static analysis is the one the runtime proves
and the generator emits, so there is no second source of truth that can drift.

**It is not a validator.** It proves the types your code declares and nothing beyond them. A rule
that is not a type — "this email is not already taken" — still needs writing, and it is not a
middleware concern: middleware is invisible to the type system and to the reader of the operation.
Rich validation belongs in [value objects](docs/types.md#value-objects) and DTOs, where the rule
travels with the type and can still [ride the same 422](docs/errors.md#your-own-validation). That
is the whole answer; there is no validation extension point because there is nothing to extend.

**JSON is the transport, and PHP's `array` is two types.** The wire format forces a decision PHP
never makes you make: `array` is both a list and a dictionary, and JSON must pick `[]` or `{}`. So
bare `array` is rejected rather than guessed at — `list<T>`, `array<int, T>` and `array<string, T>`
say which of the two you meant. The same commitment bounds the library: JSON in, JSON out, and
streams, files and other non-JSON responses are out of scope rather than unimplemented.

**No Eloquent, no Collections — on purpose.** What an Eloquent model or a Collection actually
contains is invisible to the type checker, so a contract built on one would be a guess. This
library does not work with them and is not intended to. Return plain PHP objects and shapes PHPStan
can see through, and treat the operation as your view layer: a fully typed mapping from model or
DTO to the response shape the client compiles against.

**PHPStan at level 6 or above is assumed.** The runtime proves what the annotation claims — but
only PHPStan proves the annotation against your code. Below level 6, missing parameter and return
types go unreported, and the type safety this library promises quietly stops being checked anywhere.
Most of the value is only real if static analysis is actually run, and run strictly.

**`query()` and `command()` return an `RpcError` for every failure of your operation.** An
exception from a handler or middleware — including one thrown while resolving your handler — comes
back as an `RpcError`, and `$next()` inside a middleware never throws, so post-processing runs
whether the operation succeeded or failed. The one thing that escapes as an exception is a failure
of error presentation itself (a stale class name failing reflection): that is a bug in the setup,
not a request, and burying it in a substitute 500 would only hide it.

**Seven error categories, and nothing is exposed by accident.** Surfacing a domain error takes a
`#[Throws]` declaration *and* a name; everything unrecognised is a 500. The category list is closed
on purpose — it is what the server needs to run, not an extension point.

**Runtime and codegen read the same attributes.** The server and the TypeScript error union consult
one source — the `#[Throws]` declarations resolved per scope — so the generated union cannot
describe responses the server does not produce. A declaration covers throws from its own scope only:
the operation method, or the middleware that declared it. A middleware registered globally through
`ServerConfiguration` cannot expose domain errors at all.

**Codegen is a build step.** The client lives in your repo, nothing is published to npm, and
`OutputDirectory` only ever touches files carrying its own marker — so it cannot delete or overwrite
something you wrote. `verify()` runs the same rules without writing, which is your CI drift check.
Which generators run is still your list; `CodeGenerators::fromDefaults()` is a factory over the same
contracts that spares you writing out the sensible one.

**No dishonest types.** Generation throws rather than emit a placeholder for something it cannot
represent. That is also why the envelope names `__client` but types it `unknown`: the key is
first-party, the schema belongs to whichever `Client` produced it, and claiming to know it would be
a lie.

**Directives ride the success branch only.** A handler that toasts `'Saved'` and then throws must not
have the browser announce work that did not happen, so `RpcError` holds no client at all rather than
leaving each transport to remember.

**Property order is canonical.** Struct keys are sorted by name, so reordering a PHP property or a
constructor parameter is not a change to the generated type. Enums travel as their **case names**,
not their backing values, unless the class opts in by implementing `StringValueObject`.

**A few interfaces at the seams, attributes everywhere else.** Every integration point is an
interface — `ServerAdapter`, `OperationKeyGenerator`, `OperationRegistry`, `Client`, and the
generator contracts — and those are the only contracts this library forces on you. Everything you
declare on your own code is an attribute (`#[Query]`, `#[Command]`, `#[Throws]`, `#[Brand]`, …), so
an operations class is plain PHP that extends nothing. Zero dependencies either way, and no
framework chosen for you: Laravel is an adapter over those seams, not a requirement.

## Errors

Every failure the server can produce is one of seven categories:

| Code | `type` | When |
|---|---|---|
| 422 | `INVALID_INPUT` | The input did not match its type |
| 401 | `AUTHENTICATION_ERROR` | An exception you mapped as unauthenticated |
| 403 | `AUTHORIZATION_ERROR` | An exception you mapped as unauthorized |
| 404 | `NOT_FOUND` | Unknown operation, or an exception you mapped as not-found |
| 429 | `RATE_LIMITED` | An exception you mapped as rate-limited |
| 400 | `DOMAIN_ERROR` | An exception you declared with `#[Throws]` *and* gave a name |
| 500 | `INTERNAL_ERROR` | Anything else, including an output that did not match its type |

The scope that threw is consulted first: a `#[Throws]` declaration on the throwing method — the
operation handler or a middleware's `handle()` — decides the category, and only where that scope
declared nothing do the configured category lists apply. Everything unrecognised is a 500.

A client has one more failure available to it, and no server sends it: `CLIENT_ERROR`, code 0,
minted by the generated bindings for the request that never got a real answer. The client never
trusts the HTTP status line — only a body carrying the envelope counts as the server's answer, so a
proxy's error page or a CSRF middleware's 419 becomes `CLIENT_ERROR`, carrying the cause and the
raw response. [→ The client error](docs/errors.md#the-client-error)

Exposing a domain error takes both a declaration and a name — `#[Throws]` on the throwing scope, and
either `name:` on that declaration or `#[ExposeAs]` on the exception class:

```php
#[Command('users')]
#[Throws(InvalidNameException::class, name: 'invalid-name')]
public function create(array $input): array { /* ... */ }
```

```json
{"success": false, "code": 400, "type": "DOMAIN_ERROR", "details": {"name": "invalid-name"}}
```

The generated `Failure` is a closed union — the catalogue above, parameterised only on the
domain-error names an operation exposed. Two consequences worth knowing before you meet them: an
operation that exposes nothing gets `never`, which *erases* the 400 branch entirely — against such
an operation, `result.code === 400` will not even compile — and every branch is a named type, so a
handler like `(error: ClientError | InternalError) => boolean` is written once and reused. `details`
exists only where the category cannot say everything on its own: `INVALID_INPUT` carries `fields`,
`DOMAIN_ERROR` carries the name, `RATE_LIMITED` always carries `retryIn` (seconds, or `null` when
unknown — a resolver configures the value, never the shape), everything else has none.

**[→ Errors](docs/errors.md)** — the full mechanics, the generated union, where your own validation
lives (a value object throwing `ValidationException` rides the same 422; anything the value alone
cannot decide is a named domain error — there is no hand-built 422), and the exceptions this
library throws at build time.

## Types

Most of what PHPStan can express about a shape, this library can parse, serialize and emit:

| PHPStan | TypeScript |
|---|---|
| `string`, `int`, `float`, `bool`, `null`, `mixed` | `string`, `number`, `number`, `boolean`, `null`, `unknown` |
| `numeric`, `scalar` | `(number)`, `(number\|boolean\|string)` |
| `'foo'`, `123`, `true`, `MyEnum::CASE` | `"foo"`, `123`, `true`, `"CASE"` |
| `array{name: string, age?: int}`, `object{name: string}` | `{age?:number;name:string;}` |
| `list<T>`, `T[]` | `Array<T>` |
| `array<T>`, `array<int, T>`, `array<string, T>` | `Record<string,T>` |
| `array<'a'\|'b', T>` | `Partial<Record<"a"\|"b",T>>` |
| `array{string, int}` | `[string,number]` |
| `A\|B`, `?T` | `(A\|B)`, `(null\|T)` |
| `A&B` (shapes of the same kind) | `({a:string;}&{b:number;})` |
| `MyEnum` | `("OPEN"\|"SHIPPED")` |
| `DateTimeImmutable`, `DateTimeString<'Y-m-d'>` | `string` |
| `positive-int`, `non-empty-string`, … | `number`, `string` — refinement enforced server-side |

Object properties are emitted in a canonical order, sorted by name — which is why `age` comes first
above. An enum travels as its **case names**, never its backing values — `"OPEN"`, not `'open'` —
unless the class implements `StringValueObject`; [the decisions](#the-decisions) has the reasoning
for both.

A bare `DateTimeImmutable` is ISO-8601 in, `RFC3339_EXTENDED` out: it accepts what
`Date.toISOString()` produces and writes back `2026-08-18T11:00:32.778+00:00`. Write a format —
`DateTimeString<'Y-m-d'>` — for an exact contract instead; see
[DateTimeString](docs/types.md#datetimestring).

Local and imported types work too: `@phpstan-type` and `@phpstan-import-type` are resolved against
the declaring class, as are `use` statements and generics.

**Not everything.** The parser understands a subset of PHPStan, and rejects the rest with an
`InvalidSyntaxException` when the schema is parsed — `class-string`, `key-of<T>`, `callable(…)`,
unsealed `array{foo: int, ...}`, conditional types and more. Two traps worth knowing up front: bare
`object` is a syntax error, not an alias for `unknown` (write `object{…}` with the shape), and bare
`array` is the same — nothing in it says what the elements are, so write `list<T>`, `T[]`,
`array<string, T>` or `array<int, T>`.

**[→ Full type reference](docs/types.md)** — refinements, utility types (`Pick`, `Omit`,
`BrandedString`, `DateTimeString`), value objects, `#[Castable]`, brands and named types, and the
[full list of what is not supported](docs/types.md#not-supported).

### Arrays: one PHP structure, two JavaScript ones

This is the part of the mapping where the two languages genuinely disagree, and where the library
is at its most opinionated. It is worth understanding before you write your first `@return`.

A PHP array is an ordered hash map. JSON has two collections — `[…]` and `{…}` — and `json_encode`
picks between them by looking at the keys it happens to find *at that moment*:

```php
json_encode([0 => $a, 1 => $b]);  // ["…","…"]   a JSON array
json_encode([1 => $a, 2 => $b]);  // {"1":…,"2":…}   a JSON object
json_encode([]);                  // []   an array, even for a type that is conceptually a map
```

Same declared type. Different wire shape, decided by the data. That is a client type nobody can
write down, and it is the source of the most tedious bug in any PHP/TypeScript codebase:

```php
/** @return array<int, User> */
public function active(): array
{
    return array_filter($this->users, fn (User $u) => $u->isActive());
}
```

`array_filter` preserves keys. On Monday every user is active, the keys run `0,1,2`, and the
response is `[{…},{…}]`. On Tuesday user `1` deactivates, the keys run `0,2`, and the same endpoint
answers `{"0":{…},"2":{…}}`. The client's `User[]` compiled fine and `.map` now throws in
production. Nothing in PHP warned you, because nothing in PHP was wrong.

**So the declared type decides the wire shape, never the data.**

| You write | You get | Why |
|---|---|---|
| `list<T>`, `non-empty-list<T>` | `Array<T>` | `list` is the one PHPStan type that *promises* keys `0..n-1` |
| `T[]` | `Array<T>` | by convention — see below |
| `array<T>`, `array<K, T>` | `Record<…>` | an array is a map until proven otherwise |

A record serializes as a JSON object **always** — including when it is empty, which is why the
serializer hands back a `stdClass` rather than a PHP array. `{}` and `[]` are not interchangeable
to a client, and `[]` is what a naive `json_encode` would have produced.

**`array<int, T>` is not a list.** It is the case people expect to bend, and it is exactly the one
that must not. `list<T>` promises contiguous `0..n-1`; `array<int, T>` promises only that the keys
are integers — entity ids, sparse indices, whatever `array_column($rows, null, 'id')` returned. So
it maps to `Record<string, T>`:

```php
/** @return array<int, User> */   →   Record<string, User>
```

The key is `string` and not `number` because a JSON object key *is* a string — `Record<number, T>`
reads better at a call site and then lies about what `Object.keys()` hands back. Nothing is lost on
the way back in: PHP folds a numeric string key into an int the moment it lands in an array, so
`{"42": …}` parses straight back to `[42 => …]` with an int key.

**That folding is also why a key is never coerced.** A PHP array is a hash map, and every route in
— `json_decode($json, true)`, `get_object_vars()`, an array built in PHP — has already folded
`"42"` into `42` before the executor sees it. So the key is checked exactly as it arrives, which is
exactly what it will be stored under, and the parsed array cannot disagree with the type that
declared it. The consequence is worth knowing up front: `array<string, V>` handed `{"1": …}`
**rejects** the key, because PHP has no string key `'1'` to give and accepting it would answer an
`array<int, V>` under a signature promising string keys. Only a canonical integer folds, so `"01"`,
`" 1"` and `"1.5"` are genuine string keys and stay accepted.

**`T[]` is the deliberate exception.** PHPStan reads `T[]` as `array<array-key, T>`, so by the rule
above it would be a record. It is not. In practice nobody writes `string[]` meaning a hash map —
it is universally read as "a list of strings", and honouring the letter of the spec here would
break the reasonable expectation of every codebase that uses it. This is an opinionated deviation,
and it is the only one.

**A known key set gets `Partial`.** When the keys are literals, TypeScript can say more than
`string`:

```php
/** @return array<'draft'|'live', int> */   →   Partial<Record<"draft"|"live", number>>
```

`Record<'draft'|'live', number>` would demand *both* keys be present. A PHP array keyed by
`'draft'|'live'` promises neither, so `Partial` is what is true — reading `counts.draft` gives
`number | undefined`, and building one as input lets you omit a key. Keys outside the set are
rejected when parsing input.

Refinements on the key work too and are enforced per entry on the way in:
`array<non-empty-string, V>` rejects the `""` key, `array<positive-int, V>` rejects `"0"`. Brands
on a key are dropped from the emitted type — the key travels as a property name, and a branded key
type would force a cast on every `Object.keys()` result.

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
