<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes;

use Le0daniel\PhpTsBindings\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Data\Context;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Throwable;

final readonly class CustomCastingNode implements NodeInterface
{
    public function __construct(
        public StructNode|ReferencedNode $node,
        public string                    $fullyQualifiedCastingClass,
        public ObjectCastStrategy        $strategy,
    )
    {
    }

    public function __toString(): string
    {
        return $this->fullyQualifiedCastingClass;
    }

    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $fullyQualifiedCastingClass = PHPExport::absolute($this->fullyQualifiedCastingClass) . '::class';
        $strategy = PHPExport::exportEnumCase($this->strategy);
        return "new {$className}({$this->node->exportPhpCode()}, {$fullyQualifiedCastingClass}, {$strategy})";
    }

    /**
     * @param array<string, mixed> $value
     * @param Context $context
     * @return object|Value::INVALID
     */
    public function cast(array $value, Context $context): object
    {
        try {
            if ($this->strategy === ObjectCastStrategy::CONSTRUCTOR) {
                return new ($this->fullyQualifiedCastingClass)(...$value);
            }

            $instance = new $this->fullyQualifiedCastingClass;
            foreach ($value as $key => $propertyValue) {
                $instance->{$key} = $propertyValue;
            }
            return $instance;
        } catch (Throwable $exception) {
            $context->addIssue(new Issue(
                'Internal error',
                [
                    'message' => "Failed to cast value to {$this->fullyQualifiedCastingClass}: {$exception->getMessage()}",
                    'value' => $value,
                ],
                $exception,
            ));

            return Value::INVALID;
        }
    }
}