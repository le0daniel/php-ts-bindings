<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Contracts\Attributes;

use Attribute;
use Le0daniel\PhpTsBindings\Utils\Strings;
use UnitEnum;

/**
 * Exposes a method as a write operation, reachable over POST.
 *
 * The namespace groups operations and becomes the generated TypeScript module; it defaults to
 * 'global'. The name defaults to the method name. Together they form the operation's fully
 * qualified name, which the OperationKeyGenerator turns into the key the client actually calls.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Command
{
    public function __construct(
        public string|UnitEnum|null $namespace = null,
        public ?string              $name = null,
    )
    {
    }

    public function namespaceAsString(): ?string
    {
        return $this->namespace !== null ? Strings::toString($this->namespace) : null;
    }
}