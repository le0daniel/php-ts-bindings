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
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinitionGenerator;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;

$typeString = TypeReflector::reflectParameter(
  new ReflectionParameter()
); // string|array<string, string>|object{name: string}

$parser = new TypeParser();
$ast = $parser->parse(
    $typeString, 
    // The parsing context is needed for Type Imports and used classes.
    ParsingContext::fromClassString(MyClassDeclaringThisParameter::class)
);

$inputDefinition = new TypescriptDefinitionGenerator()->toDefinition($ast, DefinitionTarget::INPUT);
// => string|Record<string>|{name: string;}

$outputDefinition = new TypescriptDefinitionGenerator()->toDefinition($ast, DefinitionTarget::OUTPUT);
// => string|Record<string>|{name: string;}

$executor = new SchemaExecutor()

// Execute against some input or output.
$parsed = $executor->parse($node, ['key' => 'value']);
$serialized = $executor->serialize($node, "my string");
```

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
use Le0daniel\PhpTsBindings\Executor\Registry\CachedTypeRegistry;

/** @var CachedTypeRegistry $registry */
$registry = require 'asts.php';

$ast = $registry->get('MyClass@methodname@input');
$otherAst = $registry->get('MyClass@methodname@output');
```

## Extending the Parser

The parser is quite simple and can be extended to support more specific types with custom parsers.

Ordering matters. The first parser that can parse the type will be used. Therefore, be careful if you prepend or append
parsers to the default parsers. For example, the datetime parser parses any DateTime interface. If you need custom logic
you need to prepend your custom parser.

```php
use Le0daniel\PhpTsBindings\Contracts\Parser;
use Le0daniel\PhpTsBindings\Parser\Definition\Token;
use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Parser\TypeParser;

class CarbonDateTimeParser implements Parser {
    
    public function canParse(string $fullyQualifiedClassName, Token $token): bool {
        return is_a($fullyQualifiedClassName, Carbon::class, true);
    }
    
    public function parse(string $fullyQualifiedClassName, Token $token, TypeParser $parser): NodeInterface {
        return new CarbonLeafNode();
    }
}

$parser = new TypeParser(
    parsers: TypeParser::getDefaultParsers(
        prepend: [
            new CarbonDateTimeParser(),
        ];    
    ),
);
```

By default, the parser uses the following parsers:

- new EnumCasesParser(): Takes an EnumClass and expects a string literal as input
- new DateTimeParser(): Takes a DateTime string, parses it as a DateTime object and serializes it as a string
- new CustomClassParser(): Takes a custom class and creates an object struct for input/output.

If you don't want to use any of the default parsers, you can pass an empty array to the constructor of TypeParser.

```php
new TypeParser(parsers: []);
```