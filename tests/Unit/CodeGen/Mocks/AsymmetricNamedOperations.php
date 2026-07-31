<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen\Mocks;

use Le0daniel\PhpTsBindings\Contracts\Attributes\Query;
use Tests\Mocks\Named\AsymmetricNamed;

/**
 * AsymmetricNamed carries one #[Named] alias but two shapes, which the generated types file cannot
 * declare honestly. The run must fail during AST validation, before a single line is emitted.
 */
final class AsymmetricNamedOperations
{
    /**
     * @param array{q: string} $input
     * @return AsymmetricNamed
     */
    #[Query('asymmetric')]
    public function get(array $input): AsymmetricNamed
    {
        return new AsymmetricNamed('secret');
    }
}
