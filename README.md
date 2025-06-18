# PHP-TS Bindings

Create simple and enforced PHP contracts with Typescript. Write strict php code and bridge the gap to your FE typescript
code seamlessly. Similar to React Server actions, but with your existing PHP code base.

## Motivation

Writing modern and statically analysable PHP is great. It provides type safety with tools like PHPStan, catching a whole
class of errors before you even deploy your application. This is great. The big issue arises at the boundry between a
modern client using typescript, where you loose type safety at the api level between PHP and TS.

Other fullstack frameworks like NextJS introduced server actions, that create full stack type safety. The dev experience
is great, allowing you to seamlessly interact with the backend and enjoy type safety throughout your stack.

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

```php
use Le0daniel\PhpTsBindings\CodeGen\Data\DefinitionTarget;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptDefinition;
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

$inputDefinition = new TypescriptDefinition()->toDefinition($ast, DefinitionTarget::INPUT);
// => string|Record<string>|{name: string;}

$outputDefinition = new TypescriptDefinition()->toDefinition($ast, DefinitionTarget::OUTPUT);
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
use Le0daniel\PhpTsBindings\Executor\Registry\CachedRegistry;

/** @var CachedRegistry $registry */
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