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

Requires **PHP 8.5** and nothing else — no dependencies, no framework coupling. A first-party
[Laravel adapter](docs/laravel.md) ships in the box and is entirely optional.

---

## Documentation

This page is the overview: install it, run it without a framework, and understand why it behaves the
way it does. Each subsystem has its own reference.

| Document | Covers |
|---|---|
| [Types](docs/types.md) | The supported PHPStan subset, refinements, utility types, value objects, `#[Castable]`, brands and named types. |
| [Operations](docs/operations.md) | The attributes, the handler contract, middleware, `ServerConfiguration`. |
| [Errors](docs/errors.md) | The six categories, exposing a domain error, the generated union, and the exceptions this library throws. |
| [The server](docs/server.md) | `Server`, operation keys, registries, DI, serving HTTP, preloading, the production cache, extension points. |
| [The TypeScript client](docs/typescript-client.md) | What codegen writes, the envelope, the transport, all eight generators, writing your own. |
| [Client directives](docs/client-directives.md) | The optional `Client` side channel for toasts, redirects and cache invalidation. |
| [The Laravel adapter](docs/laravel.md) | Config, routes, context, the `operations:*` artisan commands. |

On this page: [Install](#install) · [Quickstart](#quickstart) · [Core concepts](#core-concepts) ·
[Design decisions](#design-decisions) · [Errors](#errors) · [Types](#types) ·
[Contributing](#contributing)

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
)->generate($server, new ServerMetadata('/query/{fqn}', '/command/{fqn}'));

OutputDirectory::write(__DIR__ . '/resources/js/operations', $files);
```

`CodeGenerators::fromDefaults()` builds the five generators that are on by default, and `'name'` is
the rule that names the generated functions. `with:` and `without:` change the set — three more ship
opt-in — and what it returns is a plain list, so you can append your own or skip the factory and pass
your own array. See [the generators](docs/typescript-client.md#generators) for the whole menu.

The two URLs are the routes *your* transport serves; `{fqn}` is where the operation key goes, and
both are required to contain it.

**Serve those two routes.** One GET for queries, one POST for commands. `jsonSerialize()` is the
whole envelope the generated client reads, so a transport is two lines:

```php
use Le0daniel\PhpTsBindings\Server\Client\NullClient;

// GET /query/{fqn}  — each query parameter JSON-decoded back into a value
$result = $server->query($fqn, $input, $myContext, new NullClient());

// POST /command/{fqn}  — the JSON body
$result = $server->command($fqn, $input, $myContext, new NullClient());

respondJson($result->statusCode, $result->jsonSerialize());
```

Neither call ever throws — see [the server](docs/server.md#serving-operations-over-http) for the
full wiring, dependency injection and error reporting.

**You get a `users.ts` module**, matching the namespace:

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
`namespace` + `name` into what the client calls: `PlainlyExposedKeyGenerator` gives literal keys,
`HashSha256KeyGenerator` opaque ones. The generated TypeScript always embeds whichever key the server
produced, so this only matters when you call the server by hand — but
[pass one explicitly](docs/server.md#operation-keys), because the discovery default is peppered with
a publicly known string.

**`OperationRegistry`** holds the operations. `EagerlyLoadedOperationRegistry` discovers them by
scanning directories; schemas are parsed lazily, per operation, on first use.
`CachedOperationRegistry` is the compiled form for [production](docs/server.md#production).

**`ServerAdapter`** builds your handler classes and middleware — two methods, and the seam for
dependency injection. `NewInstanceAdapter` is the default (`new $className()`, no constructor
arguments); `PsrContainerAdapter` resolves both through a PSR-11 container. A failure to resolve is
caught and returned as an `RpcError`, which is part of what keeps the server total.

**The handler contract.** Your method is called with exactly three arguments, positionally:

```php
public function get(array $input, MyContext $context, Client $client): array
```

`$input` is the parsed, validated input — already hydrated into whatever your type declares. **Its
type is the whole input contract.** `$context` is whatever you passed to `Server::query()`; the
library never touches it. `$client` is the [side channel](docs/client-directives.md) back to the
frontend. You may declare a prefix of the three, but not a subset. An operation that takes no input
types its parameter as `null`, and every generator drops the argument.

**Input is parsed, output is serialized.** Input is untrusted, so every claim its type makes is
proven. Output is checked against its declared type too, and an output that does not match is a 500.
The PHPStan *refinements* on top of the type are not re-checked on the way out, because static
analysis already established those.

**`RpcResult`** is the interface both outcomes implement. It carries `statusCode` — 200 on success,
the error category's own code otherwise — `resolveInfo`, `metadata`, and it is `JsonSerializable`:
`jsonSerialize()` produces the whole envelope the generated client reads. A middleware can attach
metadata with `withMetadata()` / `appendMetadata()`; it travels under `__metadata` and the library
puts nothing in it.

**[→ Operations](docs/operations.md)** for the attributes, the full signature rules and middleware.
**[→ The server](docs/server.md)** for keys, registries, HTTP, preloading and the production cache.

## Design decisions

**The PHPStan type is the contract.** No schema DSL, no resource class, no generated PHP to keep
beside your code. The annotation you already wrote for static analysis is the one the runtime proves
and the generator emits, so there is no second source of truth that can drift.

**It is not a validator.** It proves the types your code declares and nothing beyond them. A rule
that is not a type — "this email is not already taken" — belongs in a
[value object](docs/types.md#value-objects) or your own code, and can still
[ride the same 422](docs/errors.md#your-own-validation).

**Input is parsed, output is serialized.** Input arrives from outside and every claim its type makes
is proven before your handler sees it. Output is your own code, so a mismatch is a 500 rather than
something the client is asked to handle — and refinements are checked on the way in only, because
static analysis already established them on the way out.

**`query()` and `command()` are total.** Every `Throwable` — including one thrown while resolving
your handler, or while working out how to present another error — comes back as an `RpcError`.
`$next()` inside a middleware never throws either, so post-processing runs whether the operation
succeeded or failed. A transport never needs a `try`.

**Six error categories, and nothing is exposed by accident.** Surfacing a domain error takes a
`#[Throws]` declaration *and* a name; everything unrecognised is a 500. The category list is closed
on purpose — it is what the server needs to run, not an extension point.

**Runtime and codegen read the same attributes.** `ErrorPresenter` and the TypeScript error union
consult one source, so the generated union cannot describe responses the server does not produce.

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

**Zero dependencies, no framework coupling.** Every integration point is an interface —
`ServerAdapter`, `OperationKeyGenerator`, `OperationRegistry`, `Client`, and the generator contracts.
Laravel is an adapter over those seams, not a requirement.

One thing obfuscated operation keys are *not* is a security boundary: they keep your operation names
out of the shipped bundle, and that is all. See [operation keys](docs/server.md#operation-keys).

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
when it is named for the client.

Exposing a domain error takes both a declaration and a name — `#[Throws]` on the operation, and
either `as:` on that declaration or `#[ExposeAs]` on the exception class:

```php
#[Command('users')]
#[Throws(InvalidNameException::class, as: 'invalid-name')]
public function create(array $input): array { /* ... */ }
```

```json
{"success": false, "code": 400, "type": "DOMAIN_ERROR", "details": {"type": "invalid-name"}}
```

Which categories an operation can produce is what the generated union says, and it says nothing else:

```typescript
export type CreateError =
    {code: 422, type: "INVALID_INPUT", details: {fields: Record<string, string[]>}}
  | {code: 404, type: "NOT_FOUND"}
  | {code: 500, type: "INTERNAL_ERROR"};
```

`details` appears only where the category cannot say everything on its own — `INVALID_INPUT` carries
`fields`, `DOMAIN_ERROR` carries `type` — and is absent everywhere else, which is exactly what the
generated branches declare.

**[→ Errors](docs/errors.md)** — the full mechanics, `InvalidInputException::createFromMessages()`
for your own validation, and the exception hierarchy this library throws at build time.

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
its backing value opts in by implementing `StringValueObject`.

Local and imported types work too: `@phpstan-type` and `@phpstan-import-type` are resolved against
the declaring class, as are `use` statements and generics.

**Not everything.** The parser understands a subset of PHPStan, and rejects the rest with an
`InvalidSyntaxException` when the schema is parsed — `class-string`, `key-of<T>`, `callable(…)`,
unsealed `array{foo: int, ...}`, conditional types and more. Two traps worth knowing up front: bare
`object` is a syntax error, not an alias for `unknown` (write `object{…}` with the shape), and bare
`array` is not `Array<unknown>` — PHPStan reads it as `array<mixed, mixed>`, which permits string
keys, so write `list<T>`, `array<int, T>` or `array<string, T>`.

**[→ Full type reference](docs/types.md)** — refinements, utility types (`Pick`, `Omit`,
`BrandedString`, `DateTimeString`), value objects, `#[Castable]`, brands and named types, and the
[full list of what is not supported](docs/types.md#not-supported).

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
