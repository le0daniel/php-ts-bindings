<?php declare(strict_types=1);

namespace Tests\Unit\Executor;

use Le0daniel\PhpTsBindings\Executor\Contracts\Result;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Issues;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Parser\Nodes\Leaf\StringNode;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;

/**
 * Failure used to extend Exception even though it is returned, never thrown. Any consumer with a
 * broad `catch (Exception)` around executor code would silently swallow a Failure that leaked out
 * of a return value, and the two arms of the result had no common type to narrow against.
 */
test('a Failure is not throwable', function () {
    expect(new Failure(new Issues()))->not->toBeInstanceOf(\Throwable::class);
});

test('isSuccess distinguishes the two arms without instanceof', function () {
    expect(new Success('value')->isSuccess())->toBeTrue()
        ->and(new Failure(new Issues())->isSuccess())->toBeFalse();
});

test('a returned failure cannot be caught as an exception', function () {
    $executor = new SchemaExecutor();

    try {
        $result = $executor->parse(new StringNode(), 42);
    } catch (\Exception $e) {
        $this->fail('A failed parse must be returned, not thrown: ' . $e::class);
    }

    expect($result)->toBeInstanceOf(Failure::class);
});

test('the executor still narrows to the concrete arms', function () {
    $executor = new SchemaExecutor();

    expect($executor->parse(new StringNode(), 'ok'))->toBeInstanceOf(Success::class)
        ->and($executor->parse(new StringNode(), 42))->toBeInstanceOf(Failure::class);
});
