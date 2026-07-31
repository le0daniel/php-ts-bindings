# PHP-TS Bindings

This library is an RPC-style library. In comparison to other libraries, it leverages PHP Stan types for input and output
definition, together with attributes to declare commands and queries.

This Library might be for you if you have a well-typed modern PHP Project and want to seamlessly communicate with the
Backend, while enjoying full stack type safety between PHP and Typescript.

## Motivation

Writing modern and statically analysable PHP is great. It provides type safety with tools like PHPStan, catching a whole
class of errors before you even deploy your application. This is great. The big issue arises at the boundary between a
modern client using TypeScript, where you loose type safety at the api level between PHP and TS.

Writing a lot of client side code with frameworks like Next.js, I really fell in love with full stack type safety. This
is a bit a challenge when using PHP, as the type system can be quite limiting at times. PHPstan comes to the help here,
but creating API resources is painful compared to Next.js server actions.

This made me think, why is there no such thing in PHP?

This library aims to provide you a similar experience for your whole stack, by leveraging modern PHP and PHPStan type
annotations, providing a clear contract between your frontend and backend. It doesn't require you to add specific code,
rather expects you to strictly type your PHP input and output types – thats it. From that, it will generate you strict
contracts and easy to use server actions and queries. As simple as that.

## Installation

```
composer require le0daniel/php-ts-bindings
```

## Usage

Get the type definition either for the PHP type system or in combination with the PHPDoc type annotations. Especially
phpstan is supported quite well, including locally defined types or imported types.

At its core, this library provides a Server class, taking a Registry of registered Operations (Commands and Queries).
They can then be run with unvalidated input. The server takes care of input validation based on your types, running
specified middlewares and returning structured output as defined in your output types. That's it. It requires your
methods to have at least an input parameter which is typed and a typed return type.

The definitions from PHPStan are parsed, applied to the provided input, guaranteeing that the input is valid based on
your types. The return type is also applied and serialized, allowing you to be really specific about what is exposed.

```php
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;

$server = new Server(
    EagerlyLoadedOperationRegistry::eagerlyDiscover('your/directory', keyGenerator: new PlainlyExposedKeyGenerator())
);

$inputData = Request::fromGlobals()->jsonInput;
$result = $server->query('users.getUser', $inputData, new MyCustomContext);
renderResponse($result);

# Class in your/directory
class MySuperClass {

    #[Query(namespace: "users")]
    #[Throws(UserNotFoundException::class)]
    /**
     * @param array{id: positive-int} $input 
     * @return object{id: int, name: string, email: string} 
     */
    public final getUser(array $input, MyCustomContext $context, Client $client): object {
        return User::findOrFail($input['id']);
    }
}
```

This provides you full type safety without any additional code. Your PHP code is fully analysable by PHPStan.

### Laravel Default Integration

We provide a first-party integration with laravel. By default, we discover remotely called functions in
`App/Operations/(.*)`. This is configurable via the config file exposed (run: `php artisan vendor:publish`) to see all
options. This lets you configure how exceptions are mapped to different buckets. Configure key generation.

Additionally, we provide code generation out of the box for laravel and your typescript project. To do so, run
`php artisan operations:codegen frontend/directory`, this will directly generate you a good starter kit for operations,
so that ou can seamlessly bridge the gap between your FE and BE. See more below for detailed codegen examples and
customizations, including writing your very own code generation plugin. 

## Type Parsing

```php
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;use Le0daniel\PhpTsBindings\Parser\TypeParser;use Le0daniel\PhpTsBindings\Reflection\TypeReflector;use Le0daniel\PhpTsBindings\Typescript\Data\IO;use Le0daniel\PhpTsBindings\Typescript\Helpers\AliasRegistry;use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;

$typeString = TypeReflector::reflectParameter(
  new ReflectionParameter()
); // string|array<string, string>|object{name: string}

$parser = new TypeParser();
$ast = $parser->parse(
    $typeString, 
    // The parsing context is needed for Type Imports and used classes.
    ParsingScope::fromClassString(MyClassDeclaringThisParameter::class)
);

$generator = new TypescriptGenerator();

$input = $generator->toTypescript($ast, IO::INPUT);
$input->type;     // => string|Record<string,string>|{name:string;}

$output = $generator->toTypescript($ast, IO::OUTPUT);
$output->type;    // => string|Record<string,string>|{name:string;}

// A #[Brand] renders inline at every use site, always parenthesised; it declares no alias.
$branded = $generator->toTypescript($parser->parse(Email::class), IO::INPUT);
$branded->type;                        // => (string & Brand<"email">)

// Named types are referenced by their alias; each definition comes back in the registry, so you
// can emit `export type Token = (string & Brand<"token">)` once and reference it everywhere.
$named = $generator->toTypescript($parser->parse("BrandedString<'token'>"), IO::INPUT);
$named->type;                          // => Token
$named->registry->toArray();           // => ['Token' => '(string & Brand<"token">)']
$named->registry->usedAliases();       // => ['Token'] — every alias in the registry counts as used

// Each call emits into its own registry — the result always carries exactly the aliases that
// schema produced. Pass an optional shared registry and every call registers its aliases into it
// at the end of the pass; that hand-over is where an alias meaning two different things across
// several schemas is rejected.
$generator->toTypescript($ast, IO::INPUT, $shared = new AliasRegistry());

$executor = new SchemaExecutor()

// Execute against some input or output.
$parsed = $executor->parse($node, ['key' => 'value']);
$serialized = $executor->serialize($node, "my string");
```

### Refinement types

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

`int-mask<…>`, `int-mask-of<…>` and `class-string` are **not** supported. Integer refinement is
`int<min, max>` and the four shorthands above, nothing else.

There is no attribute or annotation for attaching a check of your own: a property is refined by
its PHPStan type or not at all. That is what keeps a parsed schema equal to the type it was
parsed from, and it is why this library validates types rather than data — "is a valid email
address" is not something PHPStan can express, so it is not something this library checks.

### Refinements run on input, never on output

`$executor->parse()` checks every refinement. `$executor->serialize()` checks none of them, and
`SerializationOptions` has no knob to change that.

Input arrives from a client and is untrusted, so every claim its type makes has to be proven.
Output comes out of your own code, which PHPStan already analysed against the very return type
being serialized — if your method says it returns `positive-int`, static analysis has established
that. Re-checking it at runtime would cost you something for a guarantee you already have. This
library assumes static analysis does its job.

Serialization still enforces *types*: a `string` where an `int` is declared fails either way. Only
the PHPStan refinement on top of the type is skipped.

## Utility types

A handful of type names are understood in docblocks even though no such PHP class exists. They are
resolved by the bundled PHPStan extension too, so static analysis agrees with the generated types.

| Type | PHP / PHPStan | TypeScript |
| --- | --- | --- |
| `Pick<T, 'a'\|'b'>` | struct with only those properties | `{a: …; b: …;}` |
| `Omit<T, 'a'\|'b'>` | struct without those properties | `{…}` |
| `BrandedString<'name'>` | `string` | `Name`, declared as `(string & Brand<"name">)` |
| `BrandedInt<'name'>` | `int` | `Name`, declared as `(number & Brand<"name">)` |
| `DateTimeString<'format'>` | `DateTimeImmutable` | `string` |

### DateTimeString

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

```php
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

## Value Objects

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

Implementing the interface *is* the opt-in: unlike a plain class, a value object needs no `#[Castable]`
attribute and works for both input and output. Use it anywhere a type is parsed:

```php
/** @return object{id: UserId, email: Email, tags: list<Slug>} */
```

**Rejecting values.** `fromStringValue()` / `fromIntValue()` may throw to reject input. The exception is
caught and reported as a validation issue on that field, with the original exception attached for
debugging — it never reaches the client as an internal error, and never escapes the executor.

### Branded types

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
#[Brand] #[Named(io: IO::BOTH)]
final readonly class UserId implements IntValueObject { /* ... */ }
```

```typescript
export type UserId = (number & Brand<"userId">);
```

### Named types

`#[Named]` exports a class, interface, enum or value object as a named type alias: instead of
inlining the structure at every use site, the generator declares it once and references it by name.

```php
#[Named]                    // alias defaults to the class base name: App\Data\Order => Order
#[Named('CustomOrder')]     // or name it yourself
#[Named(io: IO::BOTH)]      // name input and output alike (see below)
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

Because a class can legitimately have a different input shape than output shape (constructor-only
parameters, output-only properties), the name applies to **output only by default**; on input the
structure is inlined as if the attribute were absent. Opt into `IO::BOTH` when both directions are
identical — if they are not, generation fails hard with a conflicting alias error instead of
emitting a lying type. The same error protects against two classes resolving to the same alias with
different shapes anywhere in a run, and a handful of names the generated types file always declares
(`Brand`, `Result`, `Success`, `Failure`, ...) are rejected outright.

Brands and names are pure code generation metadata with zero runtime impact: values travel the wire
in their plain shape, and the metadata is stripped from cached ASTs entirely — TypeScript
generation always runs on freshly parsed schemas. The `BrandedString<'x'>` / `BrandedInt<'x'>`
docblock utilities are the shorthand for brand + name in one, since docblocks cannot carry
attributes: `BrandedString<'token'>` is referenced as `Token` and declared as
`export type Token = (string & Brand<"token">)`.

### Sharing one declaration across value objects

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
  attributes on to *its* implementors, and neither does `class GrandChild extends Child extends
  Base`. Redeclare them on the intermediate type when you want them to keep travelling.
- **An inherited declaration cannot carry a fixed name.** `#[Brand('id')]` on `IntId` would give
  every implementor the brand `"id"` and collapse them into one type, so it is rejected at parse
  time. Drop the name to derive it per class, or compute one with a closure — see below.
- **A concrete parent keeps a brand of its own.** `#[Brand]` on a non-abstract `BaseId` brands
  `BaseId` as `baseId` *and* its children after their own names — one declaration, distinct types.

### Computing the name yourself

Both attributes accept a closure instead of a string, called with the class being emitted. It works
anywhere, but it is what makes a *shared* declaration flexible: an inherited attribute cannot carry
a fixed name, yet it can carry a rule each implementor runs against its own class name.

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

> **PHP only accepts first-class callable syntax here.** A closure literal in an attribute argument
> — `#[Named(name: static fn(string $c) => ucfirst($c))]` — does not compile: PHP reports
> *"Constant expression contains invalid operations"*. Point the closure at a named function or
> static method instead, as above.

The closure runs at parse time, never at runtime, and its result still has to be a valid TypeScript
identifier — an invalid one fails generation the same way a bad string literal does.

## Validating AST

By default, the parsed AST is not validated. This means, the AST itself can be invalid. For example Intersection types
intersecting wrong types.
You can validate the ast using the `AstValidator::validate($node)` method. This will walk through the AST and validate
each node.

## Running in Production

As with reflection class, there is quite some overhead for running the parser in production on every request.
To increase performance, you can cache and optimize your ASTs easily. The optimizer does deeper analysis on multiple
asts, reduces the object creation by splitting reused structs and types and optimizing unions for better performance.

```php
use Le0daniel\PhpTsBindings\Parser\ASTOptimizer;

$optimizer = new ASTOptimizer();
$optimizer->optimizeAndWriteToFile(
    'asts.php',
    [
        'MyClass@methodname@input' => $ast, 
        'MyClass@methodname@output' => $otherAst, 
    ],
);
```

To use the optimized ASTs, you can simply require the file in your project and use the optimized ASTs.

```php
use Le0daniel\PhpTsBindings\Parser\Registry\CachedTypeRegistry;

/** @var CachedTypeRegistry $registry */
$registry = require 'asts.php';

$ast = $registry->get('MyClass@methodname@input');
$otherAst = $registry->get('MyClass@methodname@output');
```