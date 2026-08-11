<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Errors\ErrorClassifier;
use Tests\Mocks\Errors\RecordMissingException;
use Tests\Mocks\Errors\UnexposedException;
use Tests\Mocks\Errors\UserMissingException;
use Tests\Unit\Server\Errors\Mocks\RequiresLoginInterface;
use Tests\Unit\Server\Errors\Mocks\SessionExpiredException;
use Tests\Unit\Server\Errors\Mocks\UnauthenticatedException;
use Tests\Unit\Server\Errors\Mocks\UnauthorizedException;

/**
 * @param Throwable|class-string<Throwable> $exception
 */
function classifyError(Throwable|string $exception): ErrorType
{
    return new ErrorClassifier(
        authenticationExceptions: [UnauthenticatedException::class, RequiresLoginInterface::class],
        authorizationExceptions: [UnauthorizedException::class],
        notFoundExceptions: [RecordMissingException::class],
    )->classify($exception);
}

function invalidInputException(): InvalidInputException
{
    return new InvalidInputException(new Failure(Issues::fromMessages(['name' => 'Is required'])));
}

test('an InvalidInputException instance is classified as invalid input without any configuration', function () {
    $classifier = new ErrorClassifier([], [], []);

    expect($classifier->classify(invalidInputException()))->toBe(ErrorType::INVALID_INPUT);
});

test('the InvalidInputException class-string is classified as invalid input without any configuration', function () {
    $classifier = new ErrorClassifier([], [], []);

    expect($classifier->classify(InvalidInputException::class))->toBe(ErrorType::INVALID_INPUT);
});

test('invalid input wins even when InvalidInputException is listed in a configured category', function () {
    $classifier = new ErrorClassifier([], [], [InvalidInputException::class]);

    expect($classifier->classify(invalidInputException()))->toBe(ErrorType::INVALID_INPUT);
});

test('a configured authentication exception is classified as an authentication error', function (Throwable|string $exception) {
    expect(classifyError($exception))->toBe(ErrorType::AUTHENTICATION_ERROR);
})->with([
    'instance' => [new UnauthenticatedException()],
    'class-string' => [UnauthenticatedException::class],
]);

test('an exception implementing a configured interface matches through that interface', function () {
    expect(classifyError(new SessionExpiredException()))->toBe(ErrorType::AUTHENTICATION_ERROR);
});

test('a configured authorization exception is classified as an authorization error', function () {
    expect(classifyError(new UnauthorizedException()))->toBe(ErrorType::AUTHORIZATION_ERROR);
});

test('a configured not found exception is classified as not found', function () {
    expect(classifyError(new RecordMissingException()))->toBe(ErrorType::NOT_FOUND);
});

test('a subclass of a configured exception matches its category', function () {
    expect(classifyError(new UserMissingException()))->toBe(ErrorType::NOT_FOUND);
});

test('an exception not present in any list falls back to an internal error', function (Throwable $exception) {
    expect(classifyError($exception))->toBe(ErrorType::INTERNAL_ERROR);
})->with([
    'unlisted exception' => [new UnexposedException()],
    'runtime exception' => [new RuntimeException('Something failed')],
]);

test('with no configured lists every regular exception is an internal error', function () {
    $classifier = new ErrorClassifier([], [], []);

    expect($classifier->classify(new RuntimeException('Something failed')))->toBe(ErrorType::INTERNAL_ERROR);
});

test('authentication wins when a class is configured as both authentication and authorization', function () {
    $classifier = new ErrorClassifier(
        [UnauthenticatedException::class],
        [UnauthenticatedException::class],
        [],
    );

    expect($classifier->classify(new UnauthenticatedException()))->toBe(ErrorType::AUTHENTICATION_ERROR);
});

test('authorization wins when a class is configured as both authorization and not found', function () {
    $classifier = new ErrorClassifier(
        [],
        [UnauthorizedException::class],
        [UnauthorizedException::class],
    );

    expect($classifier->classify(new UnauthorizedException()))->toBe(ErrorType::AUTHORIZATION_ERROR);
});

test('listing a subclass does not cover its parent class', function () {
    $classifier = new ErrorClassifier([], [], [UserMissingException::class]);

    expect($classifier->classify(new RecordMissingException()))->toBe(ErrorType::INTERNAL_ERROR);
});

test('an unknown class-string never throws and falls back to an internal error', function () {
    // @phpstan-ignore-next-line -- tests intentionally pass a class that does not exist.
    expect(classifyError('App\DoesNotExist'))->toBe(ErrorType::INTERNAL_ERROR);
});

test('an OperationNotFoundException is classified as not found without any configuration', function (Throwable|string $exception) {
    $classifier = new ErrorClassifier([], [], []);

    expect($classifier->classify($exception))->toBe(ErrorType::NOT_FOUND);
})->with([
    'instance' => [new OperationNotFoundException('Operation not found')],
    'class-string' => [OperationNotFoundException::class],
]);
