<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use Le0daniel\PhpTsBindings\Server\Data\MiddlewareDefinition;
use Tests\Feature\Operations\PrefixNameMiddleware;

test('middleware definition exports itself as PHP code that reconstructs identically', function () {
    $definition = new MiddlewareDefinition(PrefixNameMiddleware::class, ['prefix' => 'Dr. ', 'enabled' => true, 'limit' => 3]);

    $rebuilt = eval("return {$definition->exportPhpCode()};");

    expect($rebuilt)->toBeInstanceOf(MiddlewareDefinition::class)
        ->and($rebuilt->middleware)->toBe(PrefixNameMiddleware::class)
        ->and($rebuilt->config)->toBe(['prefix' => 'Dr. ', 'enabled' => true, 'limit' => 3]);
});

test('middleware definition without config exports without a config argument', function () {
    $definition = new MiddlewareDefinition(PrefixNameMiddleware::class);
    $exported = $definition->exportPhpCode();
    $rebuilt = eval("return {$exported};");

    expect($exported)->toBe('new \Le0daniel\PhpTsBindings\Server\Data\MiddlewareDefinition('.var_export(PrefixNameMiddleware::class, true).')')
        ->and($rebuilt->config)->toBe([]);
});
