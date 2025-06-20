<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Executor\Registry;

use InvalidArgumentException;
use Le0daniel\PhpTsBindings\Data\Endpoint;
use Le0daniel\PhpTsBindings\Data\OperationDefinition;
use Le0daniel\PhpTsBindings\Discovery\DiscoveryManager;
use Le0daniel\PhpTsBindings\Discovery\OperationDiscovery;
use Le0daniel\PhpTsBindings\Executor\Contracts\OperationRegistry;
use Le0daniel\PhpTsBindings\Parser\Data\ParsingContext;
use Le0daniel\PhpTsBindings\Parser\Exceptions\InvalidSyntaxException;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Le0daniel\PhpTsBindings\Reflection\TypeReflector;

final class JustInTimeDiscoveryRegistry implements OperationRegistry
{
    private bool $isLoaded = false;
    private OperationDiscovery $discovery;

    public function __construct(
        private readonly string $directory,
        private readonly TypeParser $parser,
    )
    {
        $this->discovery = new OperationDiscovery();
    }

    private function ensureIsDiscovered(): void
    {
        if (!$this->isLoaded) {
            new DiscoveryManager([$this->discovery,])->discover($this->directory);
            $this->isLoaded = true;
        }
    }

    public function has(string $type, string $fullyQualifiedKey): bool
    {
        $this->ensureIsDiscovered();
        return match ($type) {
            'command' => array_key_exists($fullyQualifiedKey, $this->discovery->commands),
            'query' => array_key_exists($fullyQualifiedKey, $this->discovery->queries),
        };
    }

    /**
     * @throws \ReflectionException
     * @throws InvalidSyntaxException
     */
    public function get(string $type, string $fullyQualifiedKey): Endpoint
    {
        $this->ensureIsDiscovered();
        $definition = match ($type) {
            'command' => $this->discovery->commands[$fullyQualifiedKey],
            'query' => $this->discovery->queries[$fullyQualifiedKey],
        };

        $classReflection = new \ReflectionClass($definition->fullyQualifiedClassName);
        $inputParameter = $classReflection->getMethod($definition->methodName)->getParameters()[0];

        $parsingContext = ParsingContext::fromClassReflection($classReflection);
        $input = $this->parser->parse(TypeReflector::reflectParameter($inputParameter), $parsingContext);
        $output = $this->parser->parse(TypeReflector::reflectReturnType($classReflection->getMethod($definition->methodName)), $parsingContext);

        return new Endpoint($definition, $input, $output);
    }
}