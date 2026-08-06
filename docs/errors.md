# Errors

Two error models live in this library, and they never meet. One is the finite set of categories a
client can see; the other is the exceptions the library itself throws, all of them at build time.
The short version lives in the [README](../README.md); this is the full picture.

- [The six categories](#the-six-categories)
- [Exposing a domain error](#exposing-a-domain-error)
- [The generated error union](#the-generated-error-union)
- [When `details` appears](#when-details-appears)
- [Your own validation](#your-own-validation)
- [Exceptions this library throws](#exceptions-this-library-throws)

## The six categories

Every failure the client can see is one of six:

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

The catalogue is closed. It is `ErrorType`, an `int`-backed enum whose value *is* the HTTP status
code, which is why `$result->type->value` and `$result->statusCode` cannot disagree, and why
`$result->type->name` is exactly the string the client matches on. What an application configures is
which of *its* exceptions belong in which category, with
[`ServerConfiguration::withExceptions()`](operations.md#serverconfiguration) — not which categories
exist.

## Exposing a domain error

**It takes a declaration and a name.** The operation declares that it can throw the exception, and
something gives that exception a name the client sees. The exception can carry its own:

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

`#[Throws]` on a [middleware's](operations.md#middleware) `handle()` counts as a declaration for
every operation that middleware wraps. When an operation and its middleware declare the same
exception, the operation's name wins.

## The generated error union

Because both the runtime and the code generator read those attributes from the same place, the
generated error union cannot drift from the responses it describes. An operation that declares
nothing gets:

```typescript
export type CreateError =
    {code: 422, type: "INVALID_INPUT", details: {fields: Record<string, string[]>}}
  | {code: 404, type: "NOT_FOUND"}
  | {code: 500, type: "INTERNAL_ERROR"};
```

The 401 and 403 branches appear only once you have actually mapped exceptions onto them, so the
union describes what this server can really produce.

## When `details` appears

**`details` only appears where the category cannot say everything on its own**, which is exactly two
of the six: `INVALID_INPUT` carries `fields`, and `DOMAIN_ERROR` carries the `type` naming which
domain error it is. For the other four, `code` and `type` are the whole answer and restating it
under `details` would put the same string on the wire twice, so the key is absent — and the
generated branch has no such property, so narrowing on `type` will not offer you one.

This is why `jsonSerialize()` is the only thing that gets the envelope exactly right: it omits the
key rather than sending `null`, which is what the generated union declares.

Validation failures carry `fields`, keyed by dotted path (`__root` for the top level) with
localization keys as values, e.g. `{"email": ["validation.not_empty_string"]}`.

## Your own validation

**A 422 is the schema's verdict on the input, and only that.** It is produced in exactly one place —
parsing the input against the operation's declared type — and there is no supported way to hand-build
one. `InvalidInputException` is `@internal`: you meet it as `RpcError::$cause`, you never throw it.
That is what keeps the category honest. If any code could mint a 422, `INVALID_INPUT` would stop
meaning "this did not match the type" and the client could no longer trust it to.

So a rule the type system cannot express goes in one of two places, depending on whether the value
alone decides it.

**The value decides it — put it in a value object.** "Is a valid email address", "is a positive
id": no PHPStan type says these, but nothing beyond the value itself is needed to check them. A
[value object](types.md#value-objects) throwing `ValidationException` rejects the input during
parsing, and its messages arrive in `details.fields` like any other type failure:

```php
use Le0daniel\PhpTsBindings\Executor\Exceptions\ValidationException;

public static function fromStringValue(string $value): static
{
    if (!str_contains($value, '@')) {
        throw new ValidationException('Email must contain an @');
    }

    return new self($value);
}
```

The rule now travels with the type. Every operation taking an `Email` enforces it, and none of them
had to remember to.

**Something else decides it — that is a domain error.** "Already taken" needs the database; "the
account is locked" needs the account. The input was well formed and the request still cannot
proceed, which is a 400, not a 422. Declare it with `#[Throws]` and give it a name, per
[Exposing a domain error](#exposing-a-domain-error). The client gets `details.type` naming which
rule failed, and — unlike a free-text message — the generated union makes it a case it must handle.

## Exceptions this library throws

A different model entirely: these are *not* what a client sees. Everything this library throws
implements `PhpTsBindingsException`, so one `catch` covers all of it. Below that are three subsystem
bases:

| Exception | Thrown when |
|---|---|
| `ParserException` | A schema cannot be built — includes `InvalidSyntaxException` (the type is not in the supported subset), `UnexpectedCharacterException` (it does not lex) and `UnknownTypeKeyException` (the optimized cache no longer matches the code). |
| `SchemaException` | An operation is malformed — a name or key collision, a bad handler signature, a class that is not middleware. `InvalidInputException`, `InvalidOutputException` and `OperationNotFoundException` extend it; they are `@internal` and reach you only as `RpcError::$cause`. |
| `CodeGenException` | Generation cannot produce valid output — includes `UnsupportedTypeException` (no honest TypeScript for a schema), `InvalidStringLiteralException` (a brand or alias is not an identifier) and `InvalidGeneratorDependencies` (whose `$messages` names each missing generator). |

`ValidationException` is the one exception outside those three, and the only one you are meant to
throw. The bases all mean the library could not do its job; a `ValidationException` means it did —
a [value object](types.md#value-objects) rejected a value. It never escapes the executor and never
reaches a client as itself, only as the issues it produced. Making it a `SchemaException` would mean
that catching a server fault also caught a user typing their email wrong.

Nothing is thrown out of `Server::query()` or `Server::command()` — both are total, and every
`Throwable` comes back as an `RpcError`. The exceptions above surface at discovery, at parse time or
during code generation instead, which is to say: at build time, not at request time.
