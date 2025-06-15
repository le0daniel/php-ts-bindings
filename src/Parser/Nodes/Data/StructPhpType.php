<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Data;

enum StructPhpType: string
{
    case OBJECT = 'object';
    case ARRAY = 'array';

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|object
     */
    public function coerceFromArray(array $input): array|object
    {
        return $this === self::ARRAY ? $input : (object)$input;
    }
}
