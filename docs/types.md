# Type reference

Everything this library knows how to parse, serialize and emit. The short version lives in the
[README](../README.md); this is the full picture.

- [PHP to TypeScript](#php-to-typescript)
- [Refinement types](#refinement-types)
- [Not supported](#not-supported)
- [Utility types](#utility-types)
- [DateTimeString](#datetimestring)
- [Value objects](#value-objects)
- [Plain classes and `#[Castable]`](#plain-classes-and-castable)
- [Branded types](#branded-types)
- [Named types](#named-types)
- [Sharing one declaration across value objects](#sharing-one-declaration-across-value-objects)
- [Computing the name yourself](#computing-the-name-yourself)
- [Validating the AST](#validating-the-ast)

## PHP to TypeScript

| PHPStan type | TypeScript |
| --- | --- |
| `string` | `string` |
| `int`, `float` | `number` |
| `bool` | `boolean` |
| `null` | `null` |
| `mixed` | `unknown` |
| `numeric` | `(number)` |
| `scalar` | `(number\|boolean\|string)` |
| `'foo'` | `"foo"` |
| `123`, `1.5`, `true`, `false` | `123`, `1.5`, `true`, `false` |
| `MyEnum::SUCCESS` | `"SUCCESS"` |
| `MyEnum` | `("SUCCESS"\|"FAILURE")` |
| `array{name: string}`, `object{name: string}` | `{name:string;}` |
| `array{name?: string}` | `{name?:string;}` |
| `array{a: array{b: string}}` | `{a:{b:string;};}` |
| `list<string>`, `string[]`, `array<int, string>` | `Array<string>` |
| `array<string, int>` | `Record<string,number>` |
| `array{string, int}` | `[string,number]` |
| `array{name: string}\|string` | `({name:string;}\|string)` |
| `?string` | `(null\|string)` |
| `DateTimeImmutable`, `DateTimeString<…>` | `string` |

Unions and intersections are **always** parenthesised, so a union nested inside another type can
never be misread. Members are deduplicated by their rendered form: `int\|string\|int` emits
`(number|string)`.

Object properties are emitted in a canonical order, sorted by name, so `array{name: string, age: int}`
emits `{age:number;name:string;}`. Declaration order never reaches the client, which means reordering
a PHP property or a constructor parameter does not change the generated type.

Intersections join struct shapes of the same kind — all `array{…}` or all `object{…}`. An
intersection of scalars, or one mixing the two shape kinds, is [not supported](#not-supported).

Enums emit as a union of their **case names**, not their backing values. A backed enum that should
travel as its backing value opts in by implementing `StringValueObject` — see
[value objects](#value-objects).

An enum with no cases has no TypeScript representation and fails generation.

## Refinement types

Some PHPStan types narrow a PHP type further than PHP itself can express: `positive-int` is an
`int` to PHP, `non-empty-list<T>` is an `array`. Those refinements are checked at runtime.

| PHPStan type | PHP type | Checked |
| --- | --- | --- |
| `int<min, max>` | `int` | inclusive bounds; `min` / `max` mean unbounded |
| `positive-int` | `int` | `>= 1` |
| `non-negative-int` | `int` | `>= 0` |
| `negative-int` | `int` | `<= -1` |
| `non-positive-int` | `int` | `<= 0` |
| `non-empty-string` | `string` | `!== ''` — note `"0"` is valid |
| `non-falsy-string`, `truthy-string` | `string` | truthy — `"0"` is not |
| `numeric-string` | `string` | `is_numeric()` |
| `lowercase-string` | `string` | `strtolower($v) === $v` |
| `uppercase-string` | `string` | `strtoupper($v) === $v` |
| `non-empty-lowercase-string` | `string` | both of the above |
| `non-empty-uppercase-string` | `string` | both of the above |
| `non-empty-list<T>` | `array` | at least one element |
| `non-empty-array<K, V>` | `array` | at least one element |

A refinement disappears in TypeScript — `positive-int` is `number` — because TypeScript cannot
express it either. It is enforced on the server.

Integer refinement is `int<min, max>` and the four shorthands above, nothing else — `int-mask<…>`
and `int-mask-of<…>` are [not supported](#not-supported).

There is no attribute or annotation for attaching a check of your own: a property is refined by
its PHPStan type or not at all. That is what keeps a parsed schema equal to the type it was
parsed from, and it is why this library validates types rather than data — "is a valid email
address" is not something PHPStan can express, so it is not something this library checks. When
you need that, reach for a [value object](#value-objects), whose factory may reject whatever it
likes.

### Refinements run on input, never on output

`$executor->parse()` checks every refinement. `$executor->serialize()` checks none of them, and
`SerializationOptions` has no knob to change that.

Input arrives from a client and is untrusted, so every claim its type makes has to be proven.
Output comes out of your own code, which PHPStan already analysed against the very return type
being serialized — if your method says it returns `positive-int`, static analysis has established
that. Re-checking it at runtime would cost you something for a guarantee you already have. This
library assumes static analysis does its job.

Serialization still enforces *types*: a `string` where an `int` is declared fails either way, and it
is not repaired into one — a near miss like the numeric string `"1.5"` for a `float` is reported, not
cast. Only the PHPStan refinement on top of the type is skipped.

`SerializationOptions::$partialFailures` (on by default for direct `SchemaExecutor` callers) is the
one exception to "a failure fails": with it on, a value that cannot be serialized under a
null-accepting union is replaced with `null` and the result comes back as a `Success` whose
`isPartial()` is true. It is there for best-effort serialization you intend to inspect. **The RPC
server never enables it**, because answering 200 with data the operation did not produce is not
something a client can detect.

## Not supported

The parser implements a subset of PHPStan. The subset is deliberate: every type it accepts has to
be something it can *both* check at runtime and emit as TypeScript, which rules out anything
describing PHP-side-only structure (callables, resources) or anything with no runtime
representation to check (`class-string`, conditional types).

Everything below is valid PHPStan. Writing it in a type this library parses is an error, not a
silently-degraded `unknown`.

### Rejected outright

```
array                       bare array / list / non-empty-array, without generics
list
non-empty-array
array{foo: int, ...}        unsealed array shapes
array{...}
array{}                     the empty shape
callable(int): void
Closure(int, ...): void     callable signatures
$this
Foo::*                      wildcard class-constant reference
Foo<T = int>                default generic arguments
($x is int ? string : bool) conditional types
```

PHPStan reads a bare `array` as `array<mixed, mixed>`, which permits string keys, so there is no one
TypeScript type it means: `Array<unknown>` would be wrong for a keyed array and would drop its keys
on the way out. Write `list<T>`, `array<int, T>` or `array<string, T>`.

### Not recognised at all

These reach the parser as an unknown identifier and fail with `No parser found.`:

| | |
|---|---|
| `class-string`, `class-string<T>` | `literal-string`, `interface-string` |
| `int-mask<…>`, `int-mask-of<…>` | `key-of<T>`, `value-of<T>` |
| `iterable`, `resource` | `void`, `never`, `array-key` |
| `static`, `self`, `parent` | bare `callable`, bare `Closure` |

`int-mask` / `int-mask-of` are the deliberate case: integer refinement stops at `int<min, max>` and
its shorthands, so a bitmask type has no representation here.

### Supported, but narrower than PHPStan

The traps — each is accepted by PHPStan and rejected here:

| You write | What happens |
|---|---|
| `object` | Syntax error, "Expected brace". Bare `object` is not `unknown`; write `object{…}`. |
| `array<non-empty-string, int>` | Rejected. A refined key type is not silently loosened to `string`. |
| `array{2: string, 5: int}` | Rejected. Integer-keyed tuples must run sequentially from `0`. |
| `object{0: string}` | Rejected. Object-shape keys must be identifiers or quoted strings. |
| `list{int, string}` | Psalm's keyed-list syntax. Write `array{int, string}`. |
| `A&B` where either side is not a shape | Intersections join struct shapes of the same kind — all `array{…}` or all `object{…}`, never mixed, never scalars. |
| `Foo<A, B>` on a class declaring one `@template` | Rejected. The generic count must match the declaration. |

### And no custom refinements

Worth repeating here, because it is the same boundary from the other side: there is no attribute
for attaching a check of your own — see [refinement types](#refinement-types). A property is
refined by its PHPStan type or not at all, and rules PHPStan cannot express belong in a
[value object](#value-objects).

## Utility types

A handful of type names are understood in docblocks even though no such PHP class exists. They are
resolved by the bundled PHPStan extension too, so static analysis agrees with the generated types —
[install it](../README.md#install) or these will not typecheck.

| Type | PHP / PHPStan | TypeScript |
| --- | --- | --- |
| `Pick<T, 'a'\|'b'>` | struct with only those properties | `{a: …; b: …;}` |
| `Omit<T, 'a'\|'b'>` | struct without those properties | `{…}` |
| `BrandedString<'name'>` | `string` | `Name`, declared as `(string & Brand<"name">)` |
| `BrandedInt<'name'>` | `int` | `Name`, declared as `(number & Brand<"name">)` |
| `DateTimeString<'format'>` | `DateTimeImmutable` | `string` |

`BrandedString` / `BrandedInt` are the shorthand for brand *and* name in one, because a docblock
cannot carry attributes. The same tag used twice collects one alias; the same tag resolving to two
different definitions fails the run.

## DateTimeString

`DateTimeString` is a date that travels as a string and arrives as a `DateTimeImmutable`. The
optional generic is the [PHP date format](https://www.php.net/manual/en/datetime.format.php); it
defaults to `DateTimeInterface::ATOM`.

```php
/**
 * @param DateTimeString $createdAt          // 2025-09-10T12:09:01+00:00
 * @param DateTimeString<'Y-m-d'> $birthday  // 2025-01-01
 */
public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $birthday,
) {}
```

Both are `string` in TypeScript. On input the string is parsed with the format, on output the
`DateTimeInterface` is formatted back with it.

**Prefer single quotes for the format.** Date formats escape literal characters with a backslash,
and the parser applies PHP's own string semantics: single quotes leave `\T` alone, while double
quotes resolve the full escape set. `"H:i\t"` is a tab, `'H:i\t'` is an escaped `t`.

**Parsing is strict.** The value has to match the format exactly — the parsed date is formatted
again and compared to the input. Fields the format does not cover are zeroed rather than taken
from the current clock, so `DateTimeString<'Y-m-d'>` gives you midnight, not "today at 14:32".

```
DateTimeString<'Y-m-d'>
  '2025-01-01'           // 2025-01-01 00:00:00
  '2025-1-1'             // rejected, single digit month and day
  '2025-02-30'           // rejected, would silently roll over to March 2nd
  '2025-01-01T10:00:00'  // rejected, trailing data
```

This also applies to `DateTimeImmutable`, `DateTime` and any other `DateTimeInterface` written
directly as a type.

One consequence worth knowing: ATOM renders UTC as `+00:00`, so the `Z` suffix that
`Date.toISOString()` produces is *not* accepted by the default. Use the lowercase `p` specifier,
which renders UTC as `Z`:

```php
/** @param DateTimeString<'Y-m-d\TH:i:sp'> $when */   // accepts 2025-09-10T12:09:01Z
```

## Value objects

Wrapping an id or an email in its own class usually costs you the type on the wire: a plain class is
reflected property by property, so `UserId` would show up in TypeScript as `{value: number}`. Value
objects avoid that. A class implementing `StringValueObject` or `IntValueObject` is treated as its
backing primitive — a bare `string` or `number` in JSON — and hydrated back into the class on input.

```php
use Le0daniel\PhpTsBindings\Contracts\Attributes\Brand;
use Le0daniel\PhpTsBindings\Contracts\ValueObjects\IntValueObject;

#[Brand]
final readonly class UserId implements IntValueObject
{
    private function __construct(public int $value) {}

    public static function fromIntValue(int $value): static
    {
        if ($value < 1) {
            throw new InvalidArgumentException("UserId must be positive, got {$value}");
        }
        return new self($value);
    }

    public function toIntValue(): int
    {
        return $this->value;
    }
}
```

The two interfaces are:

| Interface | Methods | JSON type |
|---|---|---|
| `StringValueObject` | `static fromStringValue(string): static`, `toStringValue(): string` | `string` |
| `IntValueObject` | `static fromIntValue(int): static`, `toIntValue(): int` | `number` |

The methods carry the `...Value` suffix so the interfaces stay safe to add to a class that already
implements `Stringable` or declares its own `toString()`.

Implementing the interface *is* the opt-in: unlike a plain class, a value object needs no
`#[Castable]` attribute and works for both input and output. Use it anywhere a type is parsed:

```php
/** @return object{id: UserId, email: Email, tags: list<Slug>} */
```

**Rejecting values.** `fromStringValue()` / `fromIntValue()` may throw to reject input. The exception
is caught and reported as a validation issue on that field, with the original exception attached for
debugging — it never reaches the client as an internal error, and never escapes the executor. This
is where "is a valid email address" belongs, since no PHPStan type can say it.

**Backed enums may opt in too.** A backed enum implementing `StringValueObject` serializes by its
backing value instead of the case-name default:

```php
enum StatusEnum: string implements StringValueObject
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function fromStringValue(string $value): static
    {
        return self::from($value);
    }

    public function toStringValue(): string
    {
        return $this->value;
    }
}
```

## Plain classes and `#[Castable]`

A class that is *not* a value object is reflected property by property. That works for output with
no ceremony. For **input** the parser has to construct it, which it will only do when you say so:

```php
use Le0daniel\PhpTsBindings\Contracts\Attributes\Castable;

#[Castable]
final class CreateUserInput
{
    public string $username;

    /** @var positive-int */
    public int $age;

    /** @var non-empty-string */
    public string $email;
}
```

Without `#[Castable]`, using the class in an input position fails generation with an
`UnsupportedTypeException` rather than emitting a type the client could never satisfy. Interfaces
and abstract classes can never be input, whatever they are annotated with.

`#[Castable]` takes an optional `ObjectCastStrategy` — `CONSTRUCTOR`, `ASSIGN_PROPERTIES` or
`NEVER`. Leave it out and the strategy is detected from the class.

**Which to reach for.** Use a value object when the type *is* one primitive with rules attached (an
id, an email, a slug) — it stays a `string` or `number` on the wire and can reject values. Use
`#[Castable]` when the type is a record of several fields you want handed to you as an object rather
than an array.

### Input and output shapes differ

The same class does not always have the same shape in both directions, which is why every schema is
emitted twice:

```php
#[Castable]
final readonly class UserSchema
{
    public function __construct(
        public int       $age,
        protected string $email,
        public string    $username,
    ) {}
}
```

```typescript
// IO::INPUT  — email is a constructor parameter, so it must be supplied
{age:number;email:string;username:string;}
// IO::OUTPUT — email is protected, so it is not readable
{age:number;username:string;}
```

Use `#[Optional]` on a property or promoted parameter to let input omit it. It needs a default value
or a nullable type to fall back on.

## Branded types

Without a brand, `UserId` and any other int are interchangeable in TypeScript. Add `#[Brand]` and the
generated type becomes opaque. A brand is an **inline** intersection at every use site — it declares
no alias of its own:

```php
#[Brand]                    // brand name defaults to lcfirst('UserId') => "userId"
#[Brand('customerId')]      // or name it yourself
```

```typescript
// declared once in the generated types file:
declare const __brand: unique symbol;
export type Brand<TBrand extends string> = {readonly [__brand]: TBrand;};

// at every use site:
declare function getUser(id: (number & Brand<"userId">)): void;
getUser(1);                 // Type error: number is not assignable to the branded type
```

`#[Brand]` works on any class, interface, enum or value object — an object shape simply becomes
`({...} & Brand<"...">)`. Combine it with `#[Named]` to export the branded type once by name:

```php
#[Brand] #[Named]
final readonly class UserId implements IntValueObject { /* ... */ }
```

```typescript
export type UserId = (number & Brand<"userId">);
```

## Named types

`#[Named]` exports a class, interface, enum or value object as a named type alias: instead of
inlining the structure at every use site, the generator declares it once and references it by name.

```php
#[Named]                             // alias defaults to the class base name: App\Data\Order => Order
#[Named('CustomOrder')]              // or name it yourself
#[Named(name: Naming::alias(...))]   // or compute it, per direction (see below)
final class Order
{
    public Customer $customer;  // Customer may itself be #[Named] — aliases nest recursively
    public UserId $id;          // and mix freely with brands
}
```

```typescript
export type Customer = {email:(string & Brand<"email">);name:string;};
export type Order = {customer:Customer;id:(number & Brand<"customerId">);};
```

**One name covers both directions.** A class can legitimately have a different input shape than
output shape — constructor-only parameters, output-only properties — and one alias cannot describe
both, because every alias is declared exactly once in the generated types file. A class whose shapes
diverge under a single name is rejected during schema generation, naming the property that made them
differ:

```php
#[Named] #[Castable]
final class Article
{
    public string $slug;                                       // output only
    public function __construct(public string $title, string $draft) { /* ... */ }
}                                                              // $draft is input only
```

> `#[Named]` on `App\Data\Article` resolves to one alias `Article` for both directions, but its input
> and output shapes differ: `draft` is input only.

Give each shape its own alias with a naming closure, which receives the direction — see
[computing the name yourself](#computing-the-name-yourself).

The same conflicting-alias error protects against two classes resolving to the same alias with
different shapes anywhere in a run. A handful of names the generated types file always declares are
rejected outright:

`Brand`, `Success`, `Failure`, `Result`, `OperationNamespaces`, `WithClientDirectives`,
`SPAClientDirectives`, `ClientDirectives`, `ClientToast`, `ClientRedirect`, `ClientInvalidation`.

Brands and names are pure code generation metadata with zero runtime impact: values travel the wire
in their plain shape, and the metadata is stripped from cached ASTs entirely — TypeScript
generation always runs on freshly parsed schemas.

## Sharing one declaration across value objects

A family of ids usually shares an interface or a base class. Declare the attributes once there and
every value object in the family picks them up:

```php
#[Brand]
#[Named]
interface IntId extends IntValueObject {}

final readonly class AccountId implements IntId { /* ... */ }
final readonly class BrandId   implements IntId { /* ... */ }
```

```typescript
export type AccountId = (number & Brand<"accountId">);
export type BrandId = (number & Brand<"brandId">);
```

The brand and the alias are derived from the **concrete** class, not from the one carrying the
attribute — which is the whole point: `AccountId` and `BrandId` share a declaration but stay
mutually unassignable in TypeScript.

Each attribute is resolved on its own, in this order:

1. **The class itself.** A local declaration always wins, and declaring both attributes locally
   means nothing else is inspected. A local `#[Brand]` combines fine with an inherited `#[Named]`.
2. **The direct parent class,** abstract or concrete.
3. **The directly declared interfaces.** Two of them declaring the same attribute is an ambiguity
   the library refuses to resolve — it fails instead of picking one. Declare the attribute on the
   class itself to say which applies.

The remaining caveats are worth reading, because each is silent otherwise:

- **Value objects only.** Enums and plain classes read the attributes from the class itself and
  nothing else, so implementing a `#[Named]` interface names nothing.
- **One level up, and no further.** `interface DeepId extends IntId` does not pass `IntId`'s
  attributes on to *its* implementors, and neither does a grandparent class. Redeclare them on the
  intermediate type when you want them to keep travelling.
- **An inherited declaration cannot carry a fixed name.** `#[Brand('id')]` on `IntId` would give
  every implementor the brand `"id"` and collapse them into one type, so it is rejected at parse
  time. Drop the name to derive it per class, or compute one with a closure — see below.
- **A concrete parent keeps a brand of its own.** `#[Brand]` on a non-abstract `BaseId` brands
  `BaseId` as `baseId` *and* its children after their own names — one declaration, distinct types.

## Computing the name yourself

Both attributes accept a closure instead of a string, called with the class being emitted. It earns
its keep twice over.

First, it is what makes a *shared* declaration flexible: an inherited attribute cannot carry a fixed
name, yet it can carry a rule each implementor runs against its own class name.

```php
final class Naming
{
    public static function alias(string $className): string
    {
        return explode('\\', $className) |> array_last(...) |> ucfirst(...);
    }
}

#[Brand]
#[Named(name: Naming::alias(...))]
interface IntId extends IntValueObject {}
```

Second, `#[Named]` calls its closure **once per direction** and hands it the `IO`, which is the only
way to give a class with two shapes two aliases:

```php
use Le0daniel\PhpTsBindings\Data\IO;

final class AliasNaming
{
    public static function perDirection(string $className, IO $io): string
    {
        $base = explode('\\', $className) |> array_last(...);
        return $io === IO::INPUT ? "{$base}Input" : $base;
    }
}

#[Named(name: AliasNaming::perDirection(...))]
#[Castable]
final class Article { /* ... */ }
```

```typescript
export type Article = {slug:string;title:string;};
export type ArticleInput = {draft:string;title:string;};
```

A closure that ignores its second argument — like `Naming::alias()` above — simply names both
directions the same, which is what almost every type wants. `#[Brand]`'s closure takes only the
class name: a brand tags one wire value, so it is the same in both directions by construction.

> **PHP only accepts first-class callable syntax here.** A closure literal in an attribute argument
> — `#[Named(name: static fn(string $c) => ucfirst($c))]` — does not compile: PHP reports
> *"Constant expression contains invalid operations"*. Point the closure at a named function or
> static method instead, as above.

The closure runs at parse time, never at runtime, and its result still has to be a valid TypeScript
identifier — an invalid one fails generation the same way a bad string literal does.

## Validating the AST

By default the parsed AST is not validated, so it is possible to build one that is internally
invalid — an intersection of types that cannot intersect, for example. Walk and check it with:

```php
use Le0daniel\PhpTsBindings\Parser\Helpers\AstValidator;

AstValidator::validate($node);
```

Code generation does this for every operation already, so a schema that survives
`operations:codegen` is valid. Call it yourself when you parse types outside the server.
