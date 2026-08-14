<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\MiddlewareDefinition;
use Le0daniel\PhpTsBindings\Server\Data\OperationType;
use Le0daniel\PhpTsBindings\Server\Errors\ThrowAttributeResolver;
use Tests\Mocks\Errors\RecordMissingException;
use Tests\Mocks\Errors\UnexposedException;
use Tests\Unit\Server\Errors\Mocks\DuplicateNamingMiddleware;
use Tests\Unit\Server\Errors\Mocks\NamedDomainExposedException;
use Tests\Unit\Server\Errors\Mocks\NamingMiddleware;
use Tests\Unit\Server\Errors\Mocks\NotFoundExposedException;
use Tests\Unit\Server\Errors\Mocks\ThrowResolverOperations;
use Tests\Unit\Server\Errors\Mocks\UnnamedTypeMiddleware;

/**
 * @return array{data: array<class-string, array{type: ErrorType, name?: string}>, issues: list<string>}
 */
function resolveThrows(string $method, bool $allowDomainErrors = true): array
{
    return ThrowAttributeResolver::resolveReflection(
        new ReflectionMethod(ThrowResolverOperations::class, $method),
        $allowDomainErrors,
    );
}

/**
 * @param  list<class-string>  $middleware
 * @return list<string>
 */
function domainErrorNamesFor(string $methodName, array $middleware = []): array
{
    return ThrowAttributeResolver::collectDomainErrorNamesFromDefinition(new Definition(
        OperationType::COMMAND,
        ThrowResolverOperations::class,
        $methodName,
        'test',
        'errors',
        // @phpstan-ignore-next-line -- tests intentionally pass classes that only carry a handle method.
        array_map(static fn (string $className): MiddlewareDefinition => new MiddlewareDefinition($className), $middleware),
    ));
}

test('a method without Throws attributes resolves to nothing', function () {
    expect(resolveThrows('declaresNothing'))->toBe(['data' => [], 'issues' => []]);
});

test('a ReflectionClass is accepted and resolves to nothing, Throws only targets methods', function () {
    $result = ThrowAttributeResolver::resolveReflection(
        new ReflectionClass(ThrowResolverOperations::class),
        true,
    );

    expect($result)->toBe(['data' => [], 'issues' => []]);
});

test('a Throws with an explicit type resolves to that type without a name', function () {
    expect(resolveThrows('declaresExplicitNotFound'))->toBe([
        'data' => [UnexposedException::class => ['type' => ErrorType::NOT_FOUND]],
        'issues' => [],
    ]);
});

test('a Throws with only a name resolves to a named domain error', function () {
    expect(resolveThrows('declaresNamedDomainError'))->toBe([
        'data' => [UnexposedException::class => ['type' => ErrorType::DOMAIN_ERROR, 'name' => 'direct_name']],
        'issues' => [],
    ]);
});

test('a Throws with an explicit domain type and a name keeps both', function () {
    expect(resolveThrows('declaresExplicitDomainWithName'))->toBe([
        'data' => [UnexposedException::class => ['type' => ErrorType::DOMAIN_ERROR, 'name' => 'explicit_domain']],
        'issues' => [],
    ]);
});

test('a bare Throws takes its type from the ExposeAs on the exception', function () {
    expect(resolveThrows('declaresViaExposeAsNotFound'))->toBe([
        'data' => [NotFoundExposedException::class => ['type' => ErrorType::NOT_FOUND]],
        'issues' => [],
    ]);
});

test('a bare Throws takes the name from the ExposeAs on the exception', function () {
    expect(resolveThrows('declaresViaExposeAsNamedDomain'))->toBe([
        'data' => [NamedDomainExposedException::class => ['type' => ErrorType::DOMAIN_ERROR, 'name' => 'exposed_domain_name']],
        'issues' => [],
    ]);
});

test('a bare Throws on an exception without ExposeAs is reported', function () {
    expect(resolveThrows('declaresUnexposed'))->toBe([
        'data' => [],
        'issues' => ['#[ExposeAs] not present on thrown class: ' . UnexposedException::class . '.'],
    ]);
});

test('a bare Throws on an exception with an invalid ExposeAs is reported', function () {
    expect(resolveThrows('declaresInvalidExposeAs'))->toBe([
        'data' => [],
        'issues' => ['#[ExposeAs] attribute declaration is not valid.'],
    ]);
});

test('a domain error declaration is rejected when domain errors are not allowed', function (string $method) {
    expect(resolveThrows($method, allowDomainErrors: false))->toBe([
        'data' => [],
        'issues' => ['Domain errors not allowed in this scope.'],
    ]);
})->with([
    'named directly on the Throws' => 'declaresNamedDomainError',
    'resolved through the ExposeAs on the exception' => 'declaresViaExposeAsNamedDomain',
]);

test('non-domain declarations resolve even when domain errors are not allowed', function (string $method, string $exceptionClass, ErrorType $type) {
    expect(resolveThrows($method, allowDomainErrors: false))->toBe([
        'data' => [$exceptionClass => ['type' => $type]],
        'issues' => [],
    ]);
})->with([
    'explicit type' => ['declaresExplicitNotFound', UnexposedException::class, ErrorType::NOT_FOUND],
    'type from ExposeAs' => ['declaresViaExposeAsNotFound', NotFoundExposedException::class, ErrorType::NOT_FOUND],
    'explicit rate limited' => ['declaresExplicitRateLimited', UnexposedException::class, ErrorType::RATE_LIMITED],
]);

test('an invalid Throws declaration is reported and not resolved', function (string $method) {
    expect(resolveThrows($method))->toBe([
        'data' => [],
        'issues' => ['#[Throw] attribute declaration is not valid.'],
    ]);
})->with([
    'a domain error without a name' => 'declaresDomainWithoutName',
    'a name on a non-domain type' => 'declaresNamedNotFound',
    // INVALID_INPUT is already rejected by isValid(), so it surfaces the generic message.
    'the invalid input type' => 'declaresInvalidInput',
]);

test('a duplicate declaration for the same exception is reported and the first one wins', function () {
    expect(resolveThrows('declaresDuplicate'))->toBe([
        'data' => [UnexposedException::class => ['type' => ErrorType::NOT_FOUND]],
        'issues' => ['Exception (' . UnexposedException::class . ') is already declared.'],
    ]);
});

test('a failed declaration does not block a later one for the same exception', function () {
    // The duplicate guard only tracks declarations that resolved: the invalid first
    // attempt is reported, and the second is not a duplicate — the first successful wins.
    expect(resolveThrows('declaresDuplicateAfterInvalid'))->toBe([
        'data' => [UnexposedException::class => ['type' => ErrorType::NOT_FOUND]],
        'issues' => ['#[Throw] attribute declaration is not valid.'],
    ]);
});

test('valid and invalid declarations on one method aggregate independently in declaration order', function () {
    expect(resolveThrows('declaresMixed'))->toBe([
        'data' => [
            UnexposedException::class => ['type' => ErrorType::NOT_FOUND],
            NamedDomainExposedException::class => ['type' => ErrorType::DOMAIN_ERROR, 'name' => 'exposed_domain_name'],
        ],
        'issues' => [
            '#[Throw] attribute declaration is not valid.',
            '#[ExposeAs] not present on thrown class: ' . RecordMissingException::class . '.',
        ],
    ]);
});

test('a bare Throws pointing at a nonexistent class escapes as a ReflectionException', function () {
    resolveThrows('declaresNonexistentClass');
})->throws(ReflectionException::class);

test('a definition declaring nothing collects no domain error names', function () {
    expect(domainErrorNamesFor('declaresNothing'))->toBe([]);
});

test('a declaration without a name never contributes a domain error name', function () {
    expect(domainErrorNamesFor('declaresExplicitNotFound'))->toBe([]);
});

test('a name declared directly on the Throws is collected', function () {
    expect(domainErrorNamesFor('declaresNamedDomainError'))->toBe(['direct_name']);
});

test('a name resolved through the ExposeAs on the exception is collected', function () {
    expect(domainErrorNamesFor('declaresViaExposeAsNamedDomain'))->toBe(['exposed_domain_name']);
});

test('only the named declarations of a mixed method are collected, its issues are discarded', function () {
    expect(domainErrorNamesFor('declaresMixed'))->toBe(['exposed_domain_name']);
});

test('a definition whose declarations all fail collects nothing instead of erroring', function () {
    expect(domainErrorNamesFor('declaresUnexposed'))->toBe([]);
});

test('a name silenced by the duplicate guard is not collected', function () {
    // declaresDuplicate resolves to the first, unnamed declaration; the named duplicate lost.
    expect(domainErrorNamesFor('declaresDuplicate'))->toBe([]);
});

test('names declared on a middleware handle method are collected', function () {
    expect(domainErrorNamesFor('declaresNothing', [NamingMiddleware::class]))->toBe(['middleware_name']);
});

test('the operation names come before the middleware names', function () {
    expect(domainErrorNamesFor('declaresNamedDomainError', [NamingMiddleware::class]))
        ->toBe(['direct_name', 'middleware_name']);
});

test('middleware names are collected in middleware order', function () {
    expect(domainErrorNamesFor('declaresNothing', [NamingMiddleware::class, DuplicateNamingMiddleware::class]))
        ->toBe(['middleware_name', 'direct_name'])
        ->and(domainErrorNamesFor('declaresNothing', [DuplicateNamingMiddleware::class, NamingMiddleware::class]))
        ->toBe(['direct_name', 'middleware_name']);
});

test('a name shared between the operation and a middleware appears once', function () {
    // DuplicateNamingMiddleware repeats the operation's 'direct_name'; the list stays deduplicated and re-indexed.
    expect(domainErrorNamesFor('declaresNamedDomainError', [DuplicateNamingMiddleware::class, NamingMiddleware::class]))
        ->toBe(['direct_name', 'middleware_name']);
});

test('a middleware declaring an unnamed type contributes no domain error name', function () {
    expect(domainErrorNamesFor('declaresNothing', [UnnamedTypeMiddleware::class]))->toBe([]);
});

test('a nonexistent middleware class escapes as a ReflectionException', function () {
    domainErrorNamesFor('declaresNothing', ['Tests\Unit\Server\Errors\Mocks\DoesNotExist']);
})->throws(ReflectionException::class);
