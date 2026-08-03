<?php declare(strict_types=1);

namespace Tests\Unit\Contracts;

use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\InvalidGeneratorDependencies;
use Le0daniel\PhpTsBindings\Contracts\PhpTsBindingsException;
use Le0daniel\PhpTsBindings\Executor\Exceptions\SchemaException;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\UnknownTypeKeyException;
use Le0daniel\PhpTsBindings\Parser\Helpers\ParsingScope;
use Le0daniel\PhpTsBindings\Parser\Lexer\Exceptions\UnexpectedCharacterException;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidMiddlewareException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidOutputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\InvalidStringLiteralException;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnknownAliasException;
use Le0daniel\PhpTsBindings\Typescript\Exceptions\UnsupportedTypeException;

/**
 * A consumer must be able to catch everything this library throws without resorting to \Throwable.
 * Without a shared ancestor there is nothing to name in a catch block, and that is not fixable
 * after v1 without a major bump.
 */
test('every exception the library declares is a PhpTsBindingsException', function (string $class) {
    expect(is_a($class, PhpTsBindingsException::class, true))->toBeTrue();
})->with([
    InvalidSyntaxException::class,
    UnknownTypeKeyException::class,
    UnexpectedCharacterException::class,
    ParserException::class,
    InvalidStringLiteralException::class,
    UnknownAliasException::class,
    UnsupportedTypeException::class,
    InvalidGeneratorDependencies::class,
    CodeGenException::class,
    InvalidInputException::class,
    InvalidOutputException::class,
    OperationNotFoundException::class,
    InvalidMiddlewareException::class,
    SchemaException::class,
]);

test('parser failures are catchable as ParserException', function (string $class) {
    expect(is_a($class, ParserException::class, true))->toBeTrue();
})->with([
    InvalidSyntaxException::class,
    UnknownTypeKeyException::class,
    UnexpectedCharacterException::class,
]);

test('code generation failures are catchable as CodeGenException', function (string $class) {
    expect(is_a($class, CodeGenException::class, true))->toBeTrue();
})->with([
    InvalidStringLiteralException::class,
    UnknownAliasException::class,
    UnsupportedTypeException::class,
    InvalidGeneratorDependencies::class,
]);

test('operation failures are catchable as SchemaException', function (string $class) {
    expect(is_a($class, SchemaException::class, true))->toBeTrue();
})->with([
    InvalidInputException::class,
    InvalidOutputException::class,
    OperationNotFoundException::class,
    InvalidMiddlewareException::class,
]);

test('the subsystem bases are distinct so a catch cannot over-capture', function () {
    expect(is_a(ParserException::class, CodeGenException::class, true))->toBeFalse()
        ->and(is_a(ParserException::class, SchemaException::class, true))->toBeFalse()
        ->and(is_a(CodeGenException::class, SchemaException::class, true))->toBeFalse();
});

/**
 * The headline case: a consumer wrapping the parser had nothing to catch but \Throwable.
 *
 * Written as a real catch rather than toThrow() because Pest treats an interface name as a message
 * to match against, so toThrow(PhpTsBindingsException::class) would pass for the wrong reason.
 */
test('a malformed type reaches the consumer as a PhpTsBindingsException', function (string $type) {
    try {
        new TypeParser()->parse($type, new ParsingScope());
        $this->fail("Expected '{$type}' to be rejected.");
    } catch (PhpTsBindingsException) {
        expect(true)->toBeTrue();
    }
})->with([
    'unterminated generic' => ['array<'],
    'unknown identifier' => ['ThisTypeDoesNotExistAnywhere'],
    'stray character' => ['string %'],
]);
