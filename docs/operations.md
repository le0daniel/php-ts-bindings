# Operations

Everything about the unit of work this library exposes: the attributes that declare one, the contract
its handler method has to satisfy, the middleware that wraps it, and the configuration that applies
to all of them. The short version lives in the [README](../README.md); this is the full picture.

- [The attributes](#the-attributes)
- [Namespaces and names](#namespaces-and-names)
- [The handler contract](#the-handler-contract)
- [Middleware](#middleware)
- [ServerConfiguration](#serverconfiguration)

## The attributes

| Attribute | Target | What it does |
|---|---|---|
| `#[Query(namespace, name)]` | method | A read operation, served over GET. |
| `#[Command(namespace, name)]` | method | A write operation, served over POST. |
| `#[Middleware(class, config?)]` | class, method, repeatable | Middleware to run around this operation, optionally with `array<string, scalar>` config. |
| `#[Throws(ExceptionClass, type: ?ErrorType, name: ?string)]` | method, repeatable | Declares an exception this method may throw — a named domain error, or an explicit category mapping. |
| `#[ExposeAs(type: ErrorType, name: ?string)]` | exception class | The exception's own category and name, for every scope that declares it. |
| `#[Optional]` | property, parameter | The field may be absent from input. |
| `#[Castable(strategy)]` | class | A plain class may be built from input. |
| `#[Brand(name)]` | class | Makes the generated TypeScript type opaque. |
| `#[Named(name)]` | class | Exports the type once by name instead of inlining it. |

`#[Throws]` and `#[ExposeAs]` are covered in [errors](errors.md#exposing-a-domain-error).
`#[Brand]`, `#[Named]`, `#[Castable]` and `#[Optional]` are covered in
[the type reference](types.md).

## Namespaces and names

`namespace` defaults to `global` and becomes the generated TypeScript module, so it has to be a
usable file name: letters, digits, `-` and `_`. It accepts a `UnitEnum` as well as a string, so you
can keep namespaces in an enum — note that a *backed* enum contributes its value and a pure enum its
case name, so adding `: string` to an existing namespace enum changes every generated module and
wire key. `name` defaults to the method name and is a string only.

Two operations of the same type resolving to the same `namespace.name` fail discovery. A query and a
command *may* share one, but both land in the same generated module, so unless the naming rule tells
them apart the code generator rejects them rather than emit two functions of the same name.

## The handler contract

Your method is called with exactly three arguments, positionally:

```php
public function get(array $input, MyContext $context, Client $client): array
```

`$input` is the parsed, validated input — already hydrated into whatever your type declares. **Its
type is the whole input contract**, so the first parameter is the one that matters. `$context` is
whatever you passed to `Server::query()`; the library never touches it. `$client` is the
[side channel](client-directives.md) back to the frontend.

**The input parameter is never optional.** A handler declaring no parameters is rejected at
discovery, and the one it does declare must carry a native type — an untyped parameter cannot be
reflected. A `@param` in the docblock overrides that native type, and is how you say anything PHP
itself cannot express.

You may declare a **prefix** of the three — `($input)` and `($input, $context)` are both fine — but
not a subset, and the prefix always starts at the input. `($input, Client $client)` receives the
context in the client slot, so discovery rejects it rather than letting it fail at runtime.

**An operation that takes no input types it as `null`.** The parameter stays; only its type changes:

```php
/**
 * @return array{ok: bool}
 */
#[Query('system')]
public function ping(null $input): array
{
    return ['ok' => true];
}
```

Every generator drops the argument for such an operation, so the TypeScript is `ping()` rather than
`ping(input)`. There is no way to omit the parameter itself.

**Input is parsed, output is serialized.** Input is untrusted, so every claim its type makes is
proven. Output is checked against its declared type too, and an output that does not match is a 500 —
it is a bug in your code, not something the client can fix. The PHPStan *refinements* on top of the
type are not re-checked, because static analysis already established those. See
[refinements run on input, never on output](types.md#refinements-run-on-input-never-on-output).

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

`ResolveInfo` describes the operation being run: `namespace`, `name`, `operationType`, `className`,
`methodName`, `middleware` (every class in the stack) and `fullyQualifiedName`.

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

`#[Throws]` on a middleware's `handle()` covers what that middleware itself throws — a declaration
never covers a throw from another scope. A middleware attached with `#[Middleware]` contributes its
named domain errors to the error union of every operation that declared it, so the generated
TypeScript knows about middleware failures too. A globally configured middleware cannot expose
domain errors at all: such a declaration is ignored at runtime and refused by code generation. It
takes `name:` like any other declaration, and since each scope names its own throws, the same
exception can surface under a different name per scope — the union carries every name.

Middleware can also attach metadata to whichever result it is holding, with `withMetadata()` /
`appendMetadata()`. It travels to the client under `__metadata` on both branches — see
[the envelope](typescript-client.md#the-envelope).

### Configuring middleware per operation

A middleware that implements `ConfigurableMiddleware` can take per-operation config from the
attribute:

```php
use Le0daniel\PhpTsBindings\Contracts\ConfigurableMiddleware;

/**
 * @implements ConfigurableMiddleware<mixed>
 */
final readonly class RateLimitMiddleware implements ConfigurableMiddleware
{
    public function __construct(public int $limit = 60) {}

    public function configure(array $config): static
    {
        return clone($this, ['limit' => (int) ($config['limit'] ?? $this->limit)]);
    }

    // handle() as usual ...
}
```

```php
#[Command('users')]
#[Middleware(RateLimitMiddleware::class, config: ['limit' => 10])]
public function create(array $input): array { /* ... */ }
```

Config is limited to `array<string, scalar>` on purpose: it is exported into the operations cache
as plain PHP code, so it must be data, not behavior. Discovery rejects any other shape, and rejects
config on a middleware that does not implement the contract.

**`configure()` runs on a private clone and returns the configured instance.** The server clones
whatever the adapter handed out before calling `configure()`, so even a container-shared instance
can never be polluted: mutable classes may assign to `$this` and return it, `readonly` classes
return `clone($this, [...])`. The configurable check happens per instance at runtime too, so an
adapter substituting a non-configurable instance for a configured declaration surfaces as a named
`RpcError` rather than an undefined-method error.

## ServerConfiguration

`ServerConfiguration` carries every server-wide setting, and is where all three are set.

`withMiddlewares()` adds [global middleware](#middleware), outermost of all.

`withExceptions()` maps your exceptions onto the [error categories](errors.md). Matching is
`instanceof`, so listing a base class covers its subclasses, and an omitted category is left
untouched:

```php
new ServerConfiguration()->withExceptions(
    notFound: [EntityNotFoundException::class],
    unauthenticated: [NotLoggedInException::class],
    unauthorized: [ForbiddenException::class],
    rateLimited: [TooManyRequestsException::class],
)
```

Without this, nothing produces a 401, 403, 404 or 429 except an unknown operation — every other
exception is a 500.

`withRetryInResolver()` gives the `RATE_LIMITED` category its `retryIn`: a closure receiving the
throwable and returning the seconds until a retry may succeed, or `null` when unknown. It is
consulted only after an error resolved as rate-limited — through the list above or a
`#[Throws(..., type: ErrorType::RATE_LIMITED)]` declaration — and without one, `details.retryIn` is
simply `null`; the [branch's shape never changes](errors.md#when-details-appears):

```php
new ServerConfiguration()->withRetryInResolver(
    fn (Throwable $throwable): ?int => $throwable instanceof TooManyRequestsException
        ? $throwable->retryAfterSeconds
        : null,
)
```

`coerceQueryInput` (default `false`) applies to **queries only**, and exists because a URL carries no
types. The generated client JSON-encodes each query value, so a transport that decodes it again
receives `?id=1` as the integer `1` and has nothing to coerce. Turn this on when requests reach you
from somewhere that does not round-trip — a hand-written URL, a form, a transport of your own — and
leaf primitives are coerced to the declared type before validation instead of failing it:

```php
new ServerConfiguration(coerceQueryInput: true)
```

Because it applies to queries only, the same input shape validates differently depending on whether
it is reached through `#[Query]` or `#[Command]`. Coercion never invents a value: only scalars are
cast, and anything else is left for the schema to reject.
