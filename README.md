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
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedRegistry;
use Le0daniel\PhpTsBindings\Contracts\Client;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Contracts\Attributes\Throws;

$server = new Server(
    EagerlyLoadedRegistry::eagerlyDiscover('your/directory', keyGenerator: new PlainlyExposedKeyGenerator())
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
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;
use Le0daniel\PhpTsBindings\Typescript\Data\IO;
use Le0daniel\PhpTsBindings\Typescript\Data\Options;
use Le0daniel\PhpTsBindings\Typescript\TypescriptGenerator;

$typeString = TypeReflector::reflectParameter(
  new ReflectionParameter()
); // string|array<string, string>|object{name: string}

$parser = new TypeParser();
$ast = $parser->parse(
    $typeString, 
    // The parsing context is needed for Type Imports and used classes.
    ParsingContext::fromClassString(MyClassDeclaringThisParameter::class)
);

$generator = new TypescriptGenerator();

$input = $generator->toTypescript($ast, IO::INPUT);
$input->type;     // => string|Record<string,string>|{name:string;}

$output = $generator->toTypescript($ast, IO::OUTPUT);
$output->type;    // => string|Record<string,string>|{name:string;}

// Branded leaves are referenced by an alias; its definition comes back in the registry, so you can
// emit `export type Email = string & Brand<"email">` once and reference it everywhere.
$branded = $generator->toTypescript($parser->parse(Email::class), IO::INPUT);
$branded->type;                        // => Email
$branded->registry->toArray();         // => ['Email' => 'string & Brand<"email">']
$branded->toStandaloneType();          // => string & Brand<"email">

// Options: pretty prints object literals across lines, ignoreBrandedTypes drops the brands and
// emits the backing primitive instead.
$generator->toTypescript($ast, IO::INPUT, new Options(pretty: true, ignoreBrandedTypes: true));

$executor = new SchemaExecutor()

// Execute against some input or output.
$parsed = $executor->parse($node, ['key' => 'value']);
$serialized = $executor->serialize($node, "my string");
```

## Utility types

A handful of type names are understood in docblocks even though no such PHP class exists. They are
resolved by the bundled PHPStan extension too, so static analysis agrees with the generated types.

| Type | PHP / PHPStan | TypeScript |
| --- | --- | --- |
| `Pick<T, 'a'\|'b'>` | struct with only those properties | `{a: …; b: …;}` |
| `Omit<T, 'a'\|'b'>` | struct without those properties | `{…}` |
| `BrandedString<'name'>` | `string` | `Name`, declared as `string & Brand<"name">` |
| `BrandedInt<'name'>` | `int` | `Name`, declared as `number & Brand<"name">` |
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
generated type becomes opaque:

```php
#[Brand]                    // brand name defaults to lcfirst('UserId') => "userId"
#[Brand('customerId')]      // or name it yourself
```

```typescript
declare const __brand: unique symbol;
export type Brand<TBrand extends string> = {readonly [__brand]: TBrand;};

export type UserId = number & Brand<"userId">;

declare function getUser(id: UserId): void;
getUser(1);                 // Type error: number is not assignable to UserId
```

Each brand is declared once, in the generated types file, and every operation that uses it references
it by that name (`{id: UserId}`) and imports it. Value objects without `#[Brand]` stay plain
`string` / `number`. Brands are code generation metadata only — they have no runtime impact, and
`php artisan operations:codegen --no-branded-types` strips them, emitting the backing primitive at
every use site and declaring nothing.

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