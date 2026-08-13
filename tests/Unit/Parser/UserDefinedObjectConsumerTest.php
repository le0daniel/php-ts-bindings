<?php

declare(strict_types=1);

use Le0daniel\PhpTsBindings\Data\IO;
use Le0daniel\PhpTsBindings\Parser\Data\Exceptions\ParserException;
use Le0daniel\PhpTsBindings\Parser\Nodes\CustomCastingNode;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\ObjectCastStrategy;
use Le0daniel\PhpTsBindings\Parser\Nodes\Data\PropertyType;
use Le0daniel\PhpTsBindings\Parser\TypeParser;
use Tests\Unit\Executor\Mocks\ApiCredentials;
use Tests\Unit\Executor\Mocks\AuditedNoteInput;
use Tests\Unit\Executor\Mocks\UpdateProfileInput;
use Tests\Unit\Executor\Mocks\UserSchema;
use Tests\Unit\Parser\Data\Stubs\CastableAbstractClass;
use Tests\Unit\Parser\Data\Stubs\ExplicitNeverCasting;
use Tests\Unit\Parser\Data\Stubs\ForcedConstructorCasting;

test('resolves the casting strategy from the attribute and constructor shape', function (string $class, ObjectCastStrategy $strategy) {
    $node = new TypeParser()->parse($class);

    expect($node)->toBeInstanceOf(CustomCastingNode::class)
        ->and($node->strategy)->toBe($strategy);

    compareToOptimizedAst($node);
    validateAst($node);
})->with([
    'constructor with arguments' => [UserSchema::class, ObjectCastStrategy::CONSTRUCTOR],
    'zero-argument constructor assigns properties' => [AuditedNoteInput::class, ObjectCastStrategy::ASSIGN_PROPERTIES],
    'no constructor assigns properties' => [UpdateProfileInput::class, ObjectCastStrategy::ASSIGN_PROPERTIES],
    'explicit strategy wins over inference' => [ExplicitNeverCasting::class, ObjectCastStrategy::NEVER],
    'abstract class is never castable' => [CastableAbstractClass::class, ObjectCastStrategy::NEVER],
]);

test('forcing the constructor strategy without a constructor fails', function () {
    expect(fn () => new TypeParser()->parse(ForcedConstructorCasting::class))
        ->toThrow(ParserException::class, 'declares none');
});

test('assign-properties classifies each property by public-scope accessibility', function () {
    /** @var CustomCastingNode $node */
    $node = new TypeParser()->parse(UpdateProfileInput::class);
    $struct = $node->node;

    expect($struct->properties)->toHaveCount(6)
        ->and($struct->getProperty('firstName')->propertyType)->toBe(PropertyType::BOTH)
        ->and($struct->getProperty('lastName')->propertyType)->toBe(PropertyType::BOTH)
        ->and($struct->getProperty('displayName')->propertyType)->toBe(PropertyType::BOTH)
        ->and($struct->getProperty('fullName')->propertyType)->toBe(PropertyType::OUTPUT)
        ->and($struct->getProperty('passwordHash')->propertyType)->toBe(PropertyType::OUTPUT)
        ->and($struct->getProperty('password')->propertyType)->toBe(PropertyType::INPUT);

    expect(typescriptFor($node, IO::INPUT)->type)
        ->toBe('{displayName:string;firstName:string;lastName:string;password:string;}')
        ->and(typescriptFor($node, IO::OUTPUT)->type)
        ->toBe('{displayName:string;firstName:string;fullName:string;lastName:string;passwordHash:string;}');
});

test('a readonly property becomes output-only instead of failing the parse', function () {
    /** @var CustomCastingNode $node */
    $node = new TypeParser()->parse(AuditedNoteInput::class);

    expect($node->strategy)->toBe(ObjectCastStrategy::ASSIGN_PROPERTIES)
        ->and($node->node->getProperty('note')->propertyType)->toBe(PropertyType::BOTH)
        ->and($node->node->getProperty('recordedBy')->propertyType)->toBe(PropertyType::OUTPUT);

    expect(typescriptFor($node, IO::INPUT)->type)->toBe('{note:string;}')
        ->and(typescriptFor($node, IO::OUTPUT)->type)->toBe('{note:string;recordedBy:string;}');
});

test('constructor strategy hides unreadable and non-public members per direction', function () {
    /** @var CustomCastingNode $node */
    $node = new TypeParser()->parse(ApiCredentials::class);
    $struct = $node->node;

    expect($node->strategy)->toBe(ObjectCastStrategy::CONSTRUCTOR)
        ->and($struct->getProperty('keyId')->propertyType)->toBe(PropertyType::BOTH)
        ->and($struct->getProperty('secret')->propertyType)->toBe(PropertyType::INPUT)
        ->and($struct->getProperty('obfuscated')->propertyType)->toBe(PropertyType::OUTPUT)
        ->and($struct->hasProperty('plainSecret'))->toBeFalse();

    expect(typescriptFor($node, IO::INPUT)->type)->toBe('{keyId:string;secret:string;}')
        ->and(typescriptFor($node, IO::OUTPUT)->type)->toBe('{keyId:string;obfuscated:string;}');
});
