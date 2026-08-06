<?php declare(strict_types=1);

namespace Tests\Adapters\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Commands\CodeGenCommand;
use Le0daniel\PhpTsBindings\CodeGen\Data\TypedOperation;
use Le0daniel\PhpTsBindings\CodeGen\Exceptions\CodeGenException;
use Mockery;
use ReflectionFunction;
use ReflectionMethod;

/**
 * A --naming rule of the kind the command resolves: an instance method, not a static one, so the
 * rule is free to depend on whatever the container injected.
 */
final class NamingRule
{
    public function __construct(public readonly string $prefix = 'app')
    {
    }

    public function name(TypedOperation $operation): string
    {
        return "{$this->prefix}_{$operation->definition->name}";
    }
}

/**
 * @param class-string|null $resolves
 */
function customNamingGeneratorFor(string $naming, ?string $resolves = null): mixed
{
    $application = Mockery::mock(Application::class);
    if ($resolves) {
        $application->shouldReceive('make')->with($resolves)->andReturn(new NamingRule('custom'));
    }

    $method = new ReflectionMethod(CodeGenCommand::class, 'customNamingGenerator');
    return $method->invoke(new CodeGenCommand(), $application, $naming);
}

test('Class::method resolves through the container and binds the instance', function () {
    $closure = customNamingGeneratorFor(NamingRule::class . '::name', NamingRule::class);

    $bound = new ReflectionFunction($closure)->getClosureThis();

    expect($bound)->toBeInstanceOf(NamingRule::class)
        ->and($bound->prefix)->toBe('custom');
});

test('an unknown naming mode ends the run with the list of valid ones', function () {
    expect(fn() => customNamingGeneratorFor('nonsense'))
        ->toThrow(CodeGenException::class, "Unknown naming mode 'nonsense'");
});

test('a Class::method naming an unknown class or method is an unknown mode', function (string $naming) {
    expect(fn() => customNamingGeneratorFor($naming))->toThrow(CodeGenException::class);
})->with([
    'App\\Nope::name',
    NamingRule::class . '::noSuchMethod',
]);
