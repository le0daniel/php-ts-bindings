<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\Laravel;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Le0daniel\PhpTsBindings\Adapters\Laravel\Client\NullClient;
use Le0daniel\PhpTsBindings\Executor\Data\Failure;
use Le0daniel\PhpTsBindings\Executor\Data\Success;
use Le0daniel\PhpTsBindings\Executor\SchemaExecutor;
use Le0daniel\PhpTsBindings\Operations\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Utils\Strings;
use UnitEnum;

final readonly class Preloader
{
    public function __construct(
        private OperationRegistry $registry,
        private SchemaExecutor    $executor,
        private Application       $application,
    )
    {
    }

    /**
     * Execute a query and returns it's result. No middlewares not input validation/serialization is called.
     * The query is simply executed and the result serialized.
     *
     * @throws Failure
     * @throws BindingResolutionException
     * @return array{result: mixed, key: list<mixed>}
     */
    public function preload(string|UnitEnum $namespace, string $name, mixed $input, mixed $context): array
    {
        $fqcn = Strings::toString($namespace) . '.' . $name;
        $operation = $this->registry->get('query', $fqcn);

        $instance = $this->application->make($operation->definition->fullyQualifiedClassName);
        $result = $instance->{$operation->definition->methodName}($input, $context, new NullClient());
        $serializedResult = $this->executor->serialize($operation->outputNode(), $result);

        if (!$result instanceof Success) {
            throw $serializedResult;
        }

        return [
            'result' => $serializedResult->value,
            'key' => [Strings::toString($namespace), $name, $input]
        ];
    }
}