<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\InvalidInputException;
use Le0daniel\PhpTsBindings\Server\Data\Exceptions\OperationNotFoundException;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Data\ResolveInfo;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Errors\ErrorPresenter;
use Tests\Mocks\Errors\ErrorOperations;
use Tests\Mocks\Errors\ExposedDomainException;
use Tests\Mocks\Errors\MiddlewareDomainException;
use Tests\Mocks\Errors\RecordMissingException;
use Tests\Mocks\Errors\RenamingMiddleware;
use Tests\Mocks\Errors\ThrowingMiddleware;
use Tests\Mocks\Errors\UndeclaredExposedException;
use Tests\Mocks\Errors\UnexposedException;
use Tests\Mocks\Errors\UserMissingException;

/**
 * @param list<class-string> $middleware
 */
function errorDefinition(string $methodName = 'declaresThrows', array $middleware = []): Definition
{
    return new Definition(
        OperationType::COMMAND,
        ErrorOperations::class,
        $methodName,
        'test',
        'errors',
        // @phpstan-ignore-next-line -- tests intentionally pass unresolvable class names.
        $middleware,
    );
}

function errorResolveInfo(): ResolveInfo
{
    return new ResolveInfo('errors', 'test', OperationType::COMMAND, ErrorOperations::class, 'declaresThrows', []);
}

test('invalid input yields a 422 carrying the field issues', function () {
    $exception = new InvalidInputException(new Failure(Issues::fromMessages(['name' => 'Is required'])));

    $error = new ErrorPresenter(new ServerConfiguration())
        ->present($exception, errorDefinition(), errorResolveInfo());

    expect($error->type)->toBe(ErrorType::INVALID_INPUT)
        ->and($error->cause)->toBe($exception)
        // The fields and nothing else: the category already says this is INVALID_INPUT.
        ->and($error->details)->toEqual([
            'fields' => $exception->failure->issues->serializeToFieldsArray(),
        ]);
});

test('a configured unauthenticated exception yields a 401', function () {
    $configuration = new ServerConfiguration()->withExceptions(unauthenticated: [RecordMissingException::class]);

    $error = new ErrorPresenter($configuration)
        ->present(new RecordMissingException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::AUTHENTICATION_ERROR)
        ->and($error->details)->toBeNull();
});

test('a configured unauthorized exception yields a 403', function () {
    $configuration = new ServerConfiguration()->withExceptions(unauthorized: [RecordMissingException::class]);

    $error = new ErrorPresenter($configuration)
        ->present(new RecordMissingException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::AUTHORIZATION_ERROR)
        ->and($error->details)->toBeNull();
});

test('a configured not found exception yields a 404', function () {
    $configuration = new ServerConfiguration()->withExceptions(notFound: [RecordMissingException::class]);

    $error = new ErrorPresenter($configuration)
        ->present(new RecordMissingException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::NOT_FOUND)
        ->and($error->details)->toBeNull();
});

test('subclasses of a configured exception match, matching is instanceof and not exact class', function () {
    $configuration = new ServerConfiguration()->withExceptions(notFound: [RecordMissingException::class]);

    $error = new ErrorPresenter($configuration)
        ->present(new UserMissingException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::NOT_FOUND);
});

test('an unknown operation yields a 404 without a definition to reflect on', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new OperationNotFoundException('nope'), null, null);

    expect($error->type)->toBe(ErrorType::NOT_FOUND)
        ->and($error->details)->toBeNull()
        ->and($error->resolveInfo)->toBeNull();
});

test('an exposed exception declared on the operation yields a 400 named after the ExposeAs type', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new ExposedDomainException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'domain_failure']);
});

test('an exposed exception declared on a middleware yields a 400', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new MiddlewareDomainException(), errorDefinition('declaresNothing', [ThrowingMiddleware::class]), null);

    expect($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'middleware_failure']);
});

test('the as name of a Throws exposes an exception that carries no ExposeAs', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new UnexposedException(), errorDefinition('declaresRenamedThrows'), null);

    expect($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'renamed_failure']);
});

test('the as name of a Throws wins over the ExposeAs on the exception', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new ExposedDomainException(), errorDefinition('declaresRenamedThrows'), null);

    expect($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'overridden_failure']);
});

test('a middleware can name the exceptions it declares too', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new MiddlewareDomainException(), errorDefinition('declaresNothing', [RenamingMiddleware::class]), null);

    expect($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'renamed_middleware_failure']);
});

test('the operation names an exception before the middleware wrapping it does', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new ExposedDomainException(), errorDefinition('declaresRenamedThrows', [RenamingMiddleware::class]), null);

    expect($error->details)->toEqual(['type' => 'overridden_failure']);
});

test('a Throws without a name never silences one that has a name', function () {
    // declaresThrows declares UnexposedException without naming it; the middleware does name it.
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new UnexposedException(), errorDefinition('declaresThrows', [RenamingMiddleware::class]), null);

    expect($error->type)->toBe(ErrorType::DOMAIN_ERROR)
        ->and($error->details)->toEqual(['type' => 'middleware_named_it']);
});

test('an as name does not exempt an exception from the configured categories', function () {
    $configuration = new ServerConfiguration()->withExceptions(notFound: [UnexposedException::class]);

    $error = new ErrorPresenter($configuration)
        ->present(new UnexposedException(), errorDefinition('declaresRenamedThrows'), null);

    expect($error->type)->toBe(ErrorType::NOT_FOUND);
});

test('a declared exception without ExposeAs falls through to the catch all', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new UnexposedException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull();
});

test('an ExposeAs exception the operation never declares falls through to the catch all', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new UndeclaredExposedException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::INTERNAL_ERROR);
});

test('an unmapped exception yields a 500 and keeps its cause and resolve info', function () {
    $exception = new RuntimeException('boom');
    $info = errorResolveInfo();

    $error = new ErrorPresenter(new ServerConfiguration())->present($exception, errorDefinition(), $info);

    expect($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull()
        ->and($error->cause)->toBe($exception)
        ->and($error->previous)->toBe([])
        ->and($error->resolveInfo)->toBe($info);
});

test('the configured categories are resolved before the exposed domain error', function () {
    $configuration = new ServerConfiguration()->withExceptions(notFound: [ExposedDomainException::class]);

    $error = new ErrorPresenter($configuration)
        ->present(new ExposedDomainException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::NOT_FOUND);
});

test('authentication and authorization are resolved before not found', function () {
    $configuration = new ServerConfiguration()->withExceptions(
        notFound: [RecordMissingException::class],
        unauthorized: [RecordMissingException::class],
    );

    $error = new ErrorPresenter($configuration)
        ->present(new RecordMissingException(), errorDefinition(), null);

    expect($error->type)->toBe(ErrorType::AUTHORIZATION_ERROR);
});

test('a definition that cannot be reflected yields a 500 instead of escaping', function () {
    $error = new ErrorPresenter(new ServerConfiguration())
        ->present(new ExposedDomainException(), errorDefinition('declaresNothing', ['Tests\Mocks\Errors\DoesNotExist']), null);

    expect($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull();
});

test('a failure to present becomes the cause and pushes the original into previous', function () {
    $exception = new ExposedDomainException();

    $error = new ErrorPresenter(new ServerConfiguration())
        ->present($exception, errorDefinition('declaresNothing', ['Tests\Mocks\Errors\DoesNotExist']), null);

    // The reflection failure is the most recent thing that went wrong, so it is the cause; the
    // exception the application threw is what came before it.
    expect($error->cause)->not->toBe($exception)
        ->and($error->previous)->toBe([$exception]);
});

test('internalError produces the last resort shape', function () {
    $exception = new RuntimeException('presenter blew up');
    $info = errorResolveInfo();

    $error = ErrorPresenter::internalError($exception, $info);

    expect($error->type)->toBe(ErrorType::INTERNAL_ERROR)
        ->and($error->details)->toBeNull()
        ->and($error->cause)->toBe($exception)
        ->and($error->previous)->toBe([])
        ->and($error->resolveInfo)->toBe($info);
});

test('internalError carries the previous failures oldest first', function () {
    $original = new RuntimeException('the application blew up');
    $latest = new RuntimeException('presenting it blew up too');

    $error = ErrorPresenter::internalError($latest, errorResolveInfo(), [$original]);

    expect($error->cause)->toBe($latest)
        ->and($error->previous)->toBe([$original])
        ->and($error->throwableChain())->toBe([$original, $latest]);
});
