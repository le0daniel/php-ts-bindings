<?php declare(strict_types=1);

namespace Tests\Unit\CodeGen;

use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationClientBindings;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperations;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitOperationsSpaClient;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitQueryKey;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTanstackQuery;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeMap;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypes;
use Le0daniel\PhpTsBindings\CodeGen\CodeGenerators\EmitTypeUtils;
use Le0daniel\PhpTsBindings\CodeGen\Data\ServerMetadata;
use Le0daniel\PhpTsBindings\CodeGen\TypescriptServerCodeGenerator;
use Le0daniel\PhpTsBindings\Server\KeyGenerators\PlainlyExposedKeyGenerator;
use Le0daniel\PhpTsBindings\Server\Operations\EagerlyLoadedOperationRegistry;
use Le0daniel\PhpTsBindings\Server\Server;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Tests\Unit\CodeGen\Mocks\TsOutput\AccountOperations;
use Tests\Unit\CodeGen\Mocks\TsOutput\CatalogOperations;
use Tests\Unit\CodeGen\Mocks\TsOutput\ShapeOperations;

/**
 * The one definition of what tests/ts-output/generated holds. The script that writes it and the
 * test that verifies it both come here, so neither can drift into checking something else.
 *
 * Every generator is registered, including the three that are opt-in on the artisan command: the
 * fixture exists to hand the TypeScript compiler as much generated code as this library can emit.
 */
final class TsOutputFixture
{
    /**
     * @var list<class-string>
     */
    public const array OPERATION_CLASSES = [
        AccountOperations::class,
        CatalogOperations::class,
        ShapeOperations::class,
    ];

    public static function directory(): string
    {
        return __DIR__ . '/../../ts-output/generated';
    }

    /**
     * @return array<string, TypescriptFile>
     */
    public static function generate(): array
    {
        $server = new Server(
            EagerlyLoadedOperationRegistry::withClasses(
                self::OPERATION_CLASSES,
                keyGenerator: new PlainlyExposedKeyGenerator(),
            ),
        );

        return new TypescriptServerCodeGenerator([
            new EmitTypes(),
            new EmitOperationClientBindings(),
            new EmitTypeUtils(),
            new EmitOperationsSpaClient(),
            new EmitOperations(),
            new EmitTypeMap(),
            new EmitTanstackQuery(),
            new EmitQueryKey(),
        ])->generate($server, new ServerMetadata('/query/{fqn}', '/command/{fqn}'));
    }
}
