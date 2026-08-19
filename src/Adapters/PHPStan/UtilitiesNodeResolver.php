<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Adapters\PHPStan;

use DateTimeImmutable;
use Override;
use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectShapeType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use ReflectionProperty;

final class UtilitiesNodeResolver implements TypeNodeResolverAwareExtension, TypeNodeResolverExtension
{
    private TypeNodeResolver $typeNodeResolver;

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    #[Override]
    public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
    {
        $this->typeNodeResolver = $typeNodeResolver;
    }

    #[Override]
    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        // DateTimeString is the one utility type usable without generics, so it is the only
        // one that has to be caught before the GenericTypeNode guard.
        if ($typeNode instanceof IdentifierTypeNode) {
            return $typeNode->name === 'DateTimeString'
                ? new ObjectType(DateTimeImmutable::class)
                : null;
        }

        if (! $typeNode instanceof GenericTypeNode) {
            // returning null means this extension is not interested in this node
            return null;
        }

        $typeName = $typeNode->type;

        return match ($typeName->name) {
            'DateTimeString' => $this->resolveDateTimeString($typeNode, $nameScope),
            'BrandedString', 'BrandedInt' => $this->resolveBrandedTypes($typeName->name, $typeNode, $nameScope),
            'Named', 'Branded' => $this->resolveNamedAndBrandedWrappers($typeNode, $nameScope),
            'Pick', 'Omit' => $this->resolvePickAndOmitUtil($typeName->name, $typeNode, $nameScope),
            default => null,
        };
    }

    /**
     * Named and Branded carry pure codegen metadata, so both resolve to their second argument.
     * Resolving through the TypeNodeResolver re-enters this extension, which is what makes
     * nesting like Branded<"accountId", Named<"AccountId", string>> work.
     */
    private function resolveNamedAndBrandedWrappers(GenericTypeNode $typeNode, NameScope $nameScope): ?Type
    {
        $arguments = $typeNode->genericTypes;
        if (count($arguments) !== 2) {
            return null;
        }

        $literalType = $this->typeNodeResolver->resolve($arguments[0], $nameScope);
        if (count($literalType->getConstantStrings()) !== 1) {
            return null;
        }

        return $this->typeNodeResolver->resolve($arguments[1], $nameScope);
    }

    /**
     * The generic argument is the date format. It carries no type information beyond having to
     * be a single constant string, so only its shape is validated here.
     */
    private function resolveDateTimeString(GenericTypeNode $typeNode, NameScope $nameScope): ?Type
    {
        $arguments = $typeNode->genericTypes;
        if (count($arguments) !== 1) {
            return null;
        }

        $formatType = $this->typeNodeResolver->resolve($arguments[0], $nameScope);
        if (count($formatType->getConstantStrings()) !== 1) {
            return null;
        }

        return new ObjectType(DateTimeImmutable::class);
    }

    private function resolveBrandedTypes(string $typeName, GenericTypeNode $typeNode, NameScope $nameScope): ?Type
    {
        $arguments = $typeNode->genericTypes;
        if (count($arguments) !== 1) {
            return null;
        }

        $literalType = $this->typeNodeResolver->resolve($arguments[0], $nameScope);

        $constStrings = $literalType->getConstantStrings();
        if (count($constStrings) !== 1) {
            return null;
        }

        return match ($typeName) {
            'BrandedString' => new StringType(),
            'BrandedInt' => new IntegerType(),
            default => null,
        };
    }

    /** @param 'Omit'|'Pick' $typeName */
    private function resolvePickAndOmitUtil(string $typeName, GenericTypeNode $typeNode, NameScope $nameScope): ?Type
    {
        $arguments = $typeNode->genericTypes;
        if (count($arguments) !== 2) {
            return null;
        }

        $structType = $this->typeNodeResolver->resolve($arguments[0], $nameScope);
        $keysType = $this->typeNodeResolver->resolve($arguments[1], $nameScope);

        if ($structType->isObject()->yes()) {
            return match ($structType::class) {
                ObjectType::class => $this->resolveObjectType($typeName, $structType, $keysType),
                ObjectShapeType::class => $this->resolveObjectShapeType($typeName, $structType, $keysType),
                default => null,
            };
        }

        if ($structType->isConstantArray()->yes()) {
            /** @phpstan-ignore-next-line argument.type */
            return $this->resolveConstArrayType($typeName, $structType, $keysType);
        }

        return null;
    }

    /**
     * @param  "Pick"|"Omit"  $type
     */
    private function resolveConstArrayType(string $type, ConstantArrayType $structType, Type $keysType): Type
    {
        $newTypeBuilder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($structType->getKeyTypes() as $i => $keyType) {
            $isPropertyInArrayStruct = match ($type) {
                'Pick' => $keysType->isSuperTypeOf($keyType)->yes(),
                'Omit' => ! $keysType->isSuperTypeOf($keyType)->yes(),
            };

            if (! $isPropertyInArrayStruct) {
                // eliminate keys that aren't in the Pick type
                continue;
            }

            $valueType = $structType->getValueTypes()[$i];
            $newTypeBuilder->setOffsetValueType(
                $keyType,
                $valueType,
                $structType->isOptionalKey($i),
            );
        }

        return $newTypeBuilder->getArray();
    }

    /**
     * @param  "Pick"|"Omit"  $type
     *
     * @throws \Exception
     */
    private function resolveObjectType(string $type, ObjectType $structType, Type $keysType): Type
    {
        $className = $structType->getClassName();
        $classReflection = $this->reflectionProvider->getClass($className);

        $properties = $classReflection->getNativeReflection()->getProperties(
            ReflectionProperty::IS_PUBLIC
        );
        $propertyTypes = [];

        /** @var \PHPStan\BetterReflection\Reflection\Adapter\ReflectionProperty $prop */
        foreach ($properties as $prop) {
            $propName = $prop->getName();
            $keyType = new ConstantStringType($propName, false);
            $isPropertyInNewObject = match ($type) {
                'Pick' => $keysType->isSuperTypeOf($keyType)->yes(),
                'Omit' => ! $keysType->isSuperTypeOf($keyType)->yes(),
            };

            if (! $isPropertyInNewObject) {
                continue;
            }

            // Ask PHPStan's ClassReflection about the property
            if ($classReflection->hasProperty($propName)) {
                $propertyReflection = $classReflection->getNativeProperty($propName);
                $propertyTypes[$propName] = $propertyReflection->getReadableType();

                continue;
            }

            throw new \Exception("Property {$propName} not found");
        }

        return new ObjectShapeType($propertyTypes, []);
    }

    /**
     * @param  "Pick"|"Omit"  $type
     */
    private function resolveObjectShapeType(string $type, ObjectShapeType $structType, Type $keysType): Type
    {
        /** @var array<string, Type> $newObjectProperties */
        $newObjectProperties = [];
        $optionalProperties = [];

        foreach ($structType->getProperties() as $propertyName => $propertyType) {
            $keyType = new ConstantStringType((string) $propertyName, false);
            $isPropertyInNewObject = match ($type) {
                'Pick' => $keysType->isSuperTypeOf($keyType)->yes(),
                'Omit' => ! $keysType->isSuperTypeOf($keyType)->yes(),
            };

            if (! $isPropertyInNewObject) {
                continue;
            }

            $newObjectProperties[$propertyName] = $propertyType;
            if (in_array($propertyName, $structType->getOptionalProperties(), true)) {
                $optionalProperties[] = $propertyName;
            }
        }

        return new ObjectShapeType($newObjectProperties, $optionalProperties);
    }
}
